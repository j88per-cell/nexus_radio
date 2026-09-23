<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Connection extends Model
{
    protected $fillable = [
        'from_type',
        'from_id',
        'to_type',
        'to_id',
        'type',
        'description',
        'year',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'from_type', 'from_id');
    }

    public function object(): MorphTo
    {
        return $this->morphTo('object', 'to_type', 'to_id');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'toured_with'   => 'Toured With',
            'guest_appearance' => 'Guest Appearance',
            'tribute_song'  => 'Tribute Song',
            'named_after'   => 'Named After',
            'co_written'    => 'Co-Written With',
            'side_project'  => 'Side Project Of',
            'split_from'    => 'Split From',
            'formed_from'   => 'Formed From Members Of',
            'collaboration' => 'Collaboration',
            default         => 'Connection',
        };
    }

    public static function allTypes(): array
    {
        return [
            'toured_with',
            'guest_appearance',
            'tribute_song',
            'named_after',
            'co_written',
            'side_project',
            'split_from',
            'formed_from',
            'collaboration',
            'other',
        ];
    }
}
