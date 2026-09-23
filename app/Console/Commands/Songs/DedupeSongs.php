<?php

namespace App\Console\Commands\Songs;

use App\Models\Song;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeSongs extends Command
{
    protected $signature = 'songs:dedupe
                            {--apply : Actually set blocked=true on the safe auto-picks. Without this, just reports.}';

    protected $description = 'Find songs sharing an exact title + artist (decades of collection building means '
        . 'real duplicate imports of the same recording) and block all but one canonical copy. Deliberately '
        . 'conservative: only exact-title matches count as duplicates (a "(Live)"/"(Acoustic)" suffix already '
        . 'makes two rows distinct on purpose), and only groups with no distinguishing evidence at all get '
        . 'auto-picked — anything with a tag or duration difference is reported for a human decision instead.';

    /** Release types preferred over others, best first — matches the tiebreak already used in export_01_songs.sql. */
    private const RELEASE_TYPE_RANK = ['album', 'compilation', 'live', 'single'];

    /** Track tags that mean "this is not interchangeable with an untagged/differently-tagged copy". */
    private const DISTINGUISHING_TAGS = ['live', 'acoustic', 'remix', 'instrumental'];

    /** Duration difference (seconds) within which two tracks are treated as "the same recording". */
    private const DURATION_TOLERANCE_SECONDS = 3;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $songs = Song::whereNull('deleted_at')
            ->where(fn($q) => $q->whereNull('blocked')->orWhere('blocked', false))
            ->with([
                'commentary',
                'releases' => fn($q) => $q->select('releases.id', 'releases.title', 'releases.type', 'releases.release_date', 'releases.artist_id'),
                'tracks'   => fn($q) => $q->whereNull('deleted_at')->select('id', 'song_id', 'duration_seconds', 'mb_duration_seconds'),
            ])
            ->get(['id', 'title', 'original_artist_id', 'song_meaning', 'story']);

        $trackTags = DB::table('track_tags')
            ->whereIn('track_id', $songs->pluck('tracks')->flatten()->pluck('id'))
            ->get()
            ->groupBy('track_id');

        $groups = [];
        foreach ($songs as $song) {
            $artistId = $song->original_artist_id ?? $song->releases->first()?->artist_id;
            if (! $artistId) {
                continue; // no artist to key on at all — leave alone, can't safely group
            }
            $key = $this->normalizeTitle($song->title) . '|' . $artistId;
            $groups[$key][] = $song;
        }

        $dupeGroups = array_filter($groups, fn($g) => count($g) > 1);

        $this->info(sprintf('%d songs scanned, %d duplicate-title groups found (%d songs total in those groups).',
            $songs->count(), count($dupeGroups), array_sum(array_map('count', $dupeGroups))));

        $safeToBlock = [];
        $needsReview = [];

        foreach ($dupeGroups as $key => $group) {
            $withCommentary = array_values(array_filter($group, fn($s) => $s->commentary->isNotEmpty()));
            if (count($withCommentary) > 1) {
                $needsReview[] = [$key, $group, $trackTags, 'multiple songs in this group have song_commentary attached'];
                continue;
            }

            $tagSets = array_map(fn($s) => $this->distinguishingTags($s, $trackTags), $group);
            $uniqueTagSets = array_unique(array_map(fn($t) => implode(',', $t), $tagSets));

            $durations = array_filter(array_map(fn($s) => $this->duration($s), $group));
            $durationsSpread = $durations ? max($durations) - min($durations) : 0;

            if (count($uniqueTagSets) > 1 || $durationsSpread > self::DURATION_TOLERANCE_SECONDS) {
                $reason = count($uniqueTagSets) > 1
                    ? 'differing track tags (' . implode(' vs ', array_unique(array_map(fn($t) => $t ?: 'untagged', $tagSets))) . ')'
                    : "duration spread {$durationsSpread}s exceeds tolerance";
                $needsReview[] = [$key, $group, $trackTags, $reason];
                continue;
            }

            $keeper = $withCommentary[0] ?? $this->pickKeeper($group);
            foreach ($group as $song) {
                if ($song->id !== $keeper->id) {
                    $safeToBlock[] = [$song, $keeper];
                }
            }
        }

        $this->line('');
        $this->info(sprintf('%d song(s) are safe auto-picks (no tag/duration difference found), keeping one canonical copy each.', count($safeToBlock)));
        foreach ($safeToBlock as [$loser, $keeper]) {
            $this->line(sprintf(
                '  BLOCK #%-6d "%s" (%s, %s)  ->  keep #%-6d (%s, %s)',
                $loser->id, $loser->title, $loser->releases->first()?->title ?? '?', $this->durationLabel($loser),
                $keeper->id, $keeper->releases->first()?->title ?? '?', $this->durationLabel($keeper)
            ));
        }

        if ($needsReview) {
            $this->line('');
            $this->warn(sprintf('%d group(s) need a human decision (real distinguishing evidence found):', count($needsReview)));
            foreach ($needsReview as [$key, $group, $tags, $reason]) {
                $this->line("  \"{$group[0]->title}\" — {$reason}");
                foreach ($group as $song) {
                    $tagList = implode(',', $this->distinguishingTags($song, $tags)) ?: 'untagged';
                    $this->line(sprintf(
                        '      #%-6d %-30s type=%-12s duration=%-6s tags=%s',
                        $song->id,
                        $song->releases->first()?->title ?? '?',
                        $song->releases->first()?->type ?? '?',
                        $this->durationLabel($song),
                        $tagList
                    ));
                }
            }
        }

        if (! $apply) {
            $this->line('');
            $this->comment('Dry run only — re-run with --apply to block the safe auto-picks above. Reviewed groups are never auto-applied, even with --apply.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($safeToBlock) {
            foreach ($safeToBlock as [$loser, $keeper]) {
                $loser->update(['blocked' => true]);
            }
        });

        $this->info(sprintf('Blocked %d songs.', count($safeToBlock)));

        return self::SUCCESS;
    }

    /**
     * Deliberately conservative — case/whitespace normalization only, no
     * stripping of "(Live)"/"(Acoustic)"/"(Remaster)" suffixes. Those are
     * real, title-level distinctions the user may want to keep (decades of
     * collecting means live/acoustic/studio versions of the same song can
     * be legitimately different, wanted entries) — only exact-title matches
     * (after case/whitespace normalization) count as duplicates here.
     */
    private function normalizeTitle(string $title): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $title)));
    }

    /** @return string[] sorted distinguishing tags found on any of this song's tracks */
    private function distinguishingTags(Song $song, $trackTagsByTrackId): array
    {
        $tags = [];
        foreach ($song->tracks as $track) {
            foreach ($trackTagsByTrackId->get($track->id, []) as $row) {
                if (in_array($row->tag, self::DISTINGUISHING_TAGS, true)) {
                    $tags[] = $row->tag;
                }
            }
        }
        $tags = array_unique($tags);
        sort($tags);
        return $tags;
    }

    private function duration(Song $song): ?int
    {
        $track = $song->tracks->first();
        return $track?->duration_seconds ?? $track?->mb_duration_seconds;
    }

    private function durationLabel(Song $song): string
    {
        $d = $this->duration($song);
        return $d ? gmdate('i:s', $d) : '?';
    }

    /**
     * Only reached for groups with no tag/duration difference — pure tiebreak
     * among what are, as far as the evidence shows, identical recordings.
     * Prefer a real album release over compilation/live/single, then earliest
     * release date, then richer story/song_meaning content, then lowest id.
     */
    private function pickKeeper(array $group)
    {
        $ranked = collect($group)->sort(function ($a, $b) {
            $rankA = $this->releaseRank($a);
            $rankB = $this->releaseRank($b);
            if ($rankA !== $rankB) return $rankA <=> $rankB;

            $dateA = $a->releases->first()?->release_date;
            $dateB = $b->releases->first()?->release_date;
            if ($dateA && $dateB && $dateA != $dateB) return $dateA <=> $dateB;
            if ($dateA xor $dateB) return $dateA ? -1 : 1;

            $contentA = mb_strlen(($a->story ?? '') . ($a->song_meaning ?? ''));
            $contentB = mb_strlen(($b->story ?? '') . ($b->song_meaning ?? ''));
            if ($contentA !== $contentB) return $contentB <=> $contentA;

            return $a->id <=> $b->id;
        });

        return $ranked->first();
    }

    private function releaseRank(Song $song): int
    {
        $type = $song->releases->first()?->type;
        $idx  = array_search($type, self::RELEASE_TYPE_RANK, true);
        return $idx === false ? count(self::RELEASE_TYPE_RANK) : $idx;
    }
}
