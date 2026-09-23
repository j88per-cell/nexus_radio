<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StationEvent extends Model
{
    public $timestamps = false;

    const IMPACT_POSITIVE = 'positive';
    const IMPACT_NEGATIVE = 'negative';
    const IMPACT_NEUTRAL  = 'neutral';

    protected $fillable = [
        'description',
        'mood_impact',
        'magnitude',
        'probability',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active'      => 'boolean',
            'probability' => 'float',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
