<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseCredit extends Model
{
    protected $fillable = [
        'release_id',
        'person_id',
        'role',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
