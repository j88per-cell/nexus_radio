<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyStat extends Model
{
    protected $fillable = [
        'year',
        'month',
        'total_plays',
        'top_artists',
        'top_genres',
    ];

    protected function casts(): array
    {
        return [
            'top_artists' => 'array',
            'top_genres'  => 'array',
        ];
    }
}
