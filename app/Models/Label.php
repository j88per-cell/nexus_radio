<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'founded_year',
        'country',
        'notes',
    ];

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class, 'artist_labels')
            ->withPivot(['start_year', 'end_year', 'notes'])
            ->withTimestamps();
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'release_labels');
    }
}
