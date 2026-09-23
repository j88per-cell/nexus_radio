<?php

namespace App\Console\Commands\Songs;

use App\Models\Song;
use Illuminate\Console\Command;

class CleanTitles extends Command
{
    protected $signature = 'songs:clean-titles
                            {--apply : Actually save changes (default is dry-run)}
                            {--reset-lyrics : Reset lyrics_attempts to 0 on changed songs that had given up, so they get retried}';

    protected $description = 'Strip leading track numbers and "Artist-" prefixes baked into song titles by some import sources. Title only — never touches file_path.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $songs = Song::with('tracks.release.artist')->get();

        $changes = [];

        foreach ($songs as $song) {
            $track      = $song->tracks->first();
            $artistName = $track?->release?->artist?->name;
            $cleaned    = $this->clean($song->title, $artistName, $track?->position);

            if ($cleaned !== $song->title) {
                $changes[] = [$song, $song->title, $cleaned];
            }
        }

        if (empty($changes)) {
            $this->info('No song titles need cleaning.');
            return self::SUCCESS;
        }

        foreach ($changes as [$song, $old, $new]) {
            $this->line("  [{$song->id}] \"{$old}\" -> \"{$new}\"");
        }

        $this->info(count($changes) . ' title(s) ' . ($apply ? 'updated.' : 'would be updated (dry-run — pass --apply to save).'));

        if (! $apply) {
            return self::SUCCESS;
        }

        $resetLyrics = (bool) $this->option('reset-lyrics');
        $resetCount  = 0;

        foreach ($changes as [$song, $old, $new]) {
            $song->title = $new;

            if ($resetLyrics && $song->lyrics === null && $song->lyrics_attempts > 0) {
                $song->lyrics_attempts = 0;
                $resetCount++;
            }

            $song->save();
        }

        if ($resetLyrics) {
            $this->info("Reset lyrics_attempts on {$resetCount} changed song(s) that had given up.");
        }

        return self::SUCCESS;
    }

    /** Titles the import falls back to when it couldn't read real metadata — the
     *  leading number is the only thing telling these apart, so never strip it. */
    private const PLACEHOLDER_TITLES = ['unknown track', 'untitled', 'track'];

    private function clean(string $title, ?string $artistName, ?int $trackPosition): string
    {
        $bareTitle = preg_replace('/^\d{1,2}[\s.\-]+/', '', $title);
        if (in_array(mb_strtolower(trim($bareTitle)), self::PLACEHOLDER_TITLES, true)) {
            return $title;
        }

        $cleaned = $title;

        // Leading track number: "08 Title", "08. Title", "12 - Title". Only strip
        // if the leading digits actually match this track's real position — plenty
        // of real song titles start with a number too ("24 Hours Ago", "7 Days to
        // the Wolves"), so matching on digit-shape alone is not safe.
        if ($trackPosition !== null) {
            $variants = array_unique([(string) $trackPosition, sprintf('%02d', $trackPosition)]);
            usort($variants, fn($a, $b) => strlen($b) <=> strlen($a));
            $pattern = '/^(' . implode('|', array_map('preg_quote', $variants)) . ')[\s.\-]+/';
            $cleaned = preg_replace($pattern, '', $cleaned);
        }

        // Artist name baked directly into the title by the import source,
        // with no space before the dash: "Doro & Warlock-Beyond the trees"
        if ($artistName) {
            $cleaned = preg_replace(
                '/^' . preg_quote($artistName, '/') . '\s*-\s*/i',
                '',
                $cleaned
            );
        }

        $cleaned = trim($cleaned);

        // Re-capitalize the first letter if stripping shifted a lowercase
        // continuation word ("All we are") to the front of the title.
        if ($cleaned !== '' && $cleaned !== $title) {
            $cleaned = mb_strtoupper(mb_substr($cleaned, 0, 1)) . mb_substr($cleaned, 1);
        }

        return $cleaned === '' ? $title : $cleaned;
    }
}
