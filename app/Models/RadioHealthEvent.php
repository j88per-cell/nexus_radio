<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadioHealthEvent extends Model
{
    const EVENT_STALLED         = 'stalled';
    const EVENT_RECOVERED       = 'recovered';
    const EVENT_WATCHDOG_RESTART = 'watchdog_restart';

    protected $fillable = [
        'event',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
