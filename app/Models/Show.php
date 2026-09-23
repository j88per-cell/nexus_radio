<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Show extends Model
{
    // Modes
    const MODE_MANUAL = 'manual';
    const MODE_AUTO   = 'auto';

    // Statuses
    const STATUS_DRAFT  = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_LIVE   = 'live';
    const STATUS_DONE   = 'done';

    // Recurrence types
    const RECURRENCE_ONCE     = 'once';
    const RECURRENCE_INTERVAL = 'interval';
    const RECURRENCE_DAILY    = 'daily';
    const RECURRENCE_WEEKLY   = 'weekly';

    protected $fillable = [
        'name',
        'description',
        'theme',
        'mode',
        'status',
        'priority',
        'recurrence',
        'scheduled_at',
        'start_time',
        'duration_minutes',
        'recurrence_days',
        'interval_hours',
        'next_run_at',
        'last_run_at',
        'live_until',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_days' => 'array',
            'scheduled_at'    => 'datetime',
            'next_run_at'     => 'datetime',
            'last_run_at'     => 'datetime',
            'live_until'      => 'datetime',
        ];
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(ShowTrack::class)->orderBy('position');
    }

    /**
     * Total seconds of all assigned tracks (manual shows).
     */
    public function getTotalDurationSecondsAttribute(): int
    {
        return $this->tracks->sum('duration_seconds');
    }

    /**
     * Compute and return the next run datetime after the given reference point.
     * Does NOT save — call $show->update(['next_run_at' => ...]) explicitly.
     */
    public function computeNextRunAt(?Carbon $after = null): ?Carbon
    {
        $after ??= Carbon::now();

        return match ($this->recurrence) {
            self::RECURRENCE_ONCE     => $this->scheduled_at,
            self::RECURRENCE_INTERVAL => $after->copy()->addHours($this->interval_hours ?? 12),
            self::RECURRENCE_DAILY    => $this->nextOccurrence($after, null),
            self::RECURRENCE_WEEKLY   => $this->nextOccurrence($after, $this->recurrence_days ?? []),
            default                   => null,
        };
    }

    /**
     * Find the next wall-clock occurrence at $this->start_time, optionally
     * restricted to specific days of week (0=Mon … 6=Sun).
     */
    private function nextOccurrence(Carbon $after, ?array $days): Carbon
    {
        $tz = config('app.schedule_timezone', 'America/Denver');

        [$hour, $minute] = explode(':', $this->start_time ?? '00:00');

        // Build the candidate in the local schedule timezone so "13:00" means
        // 1pm Mountain, not 1pm UTC.
        $candidate = $after->copy()->setTimezone($tz)->setTime((int) $hour, (int) $minute, 0);

        if ($candidate->lte($after)) {
            $candidate->addDay();
        }

        if ($days === null) {
            return $candidate;
        }

        // days are 0=Mon…6=Sun; Carbon->dayOfWeek is 0=Sun…6=Sat, so we convert
        $limit = 7;
        while ($limit-- > 0) {
            // Carbon dayOfWeek: 0=Sun, 1=Mon … 6=Sat → convert to Mon-based
            $dow = ($candidate->dayOfWeek + 6) % 7;
            if (in_array($dow, $days)) {
                return $candidate;
            }
            $candidate->addDay();
        }

        return $candidate;
    }

    public function isRepeating(): bool
    {
        return in_array($this->recurrence, [
            self::RECURRENCE_INTERVAL,
            self::RECURRENCE_DAILY,
            self::RECURRENCE_WEEKLY,
        ]);
    }

}
