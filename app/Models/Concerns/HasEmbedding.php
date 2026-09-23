<?php

namespace App\Models\Concerns;

use Pgvector\Laravel\Vector;

trait HasEmbedding
{
    protected function embeddingCast(): array
    {
        return ['embedding' => Vector::class];
    }

    public function similarTo(array $vector, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->orderByRaw('embedding <=> ?', [new Vector($vector)])
            ->limit($limit)
            ->get();
    }
}
