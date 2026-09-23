<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Persistent cross-entity ID mapping for ETL import.
 *
 * Stores navidrome string IDs → new integer PKs in the import_id_maps table
 * so individual import commands can be re-run independently.
 *
 * Entity types: 'artist', 'release', 'song', 'track'
 */
class ImportIdMap
{
    public static function put(string $type, string $oldId, int $newId): void
    {
        DB::table('import_id_maps')->upsert(
            [['entity_type' => $type, 'old_id' => $oldId, 'new_id' => $newId]],
            ['entity_type', 'old_id'],
            ['new_id']
        );
    }

    public static function get(string $type, string $oldId): ?int
    {
        return DB::table('import_id_maps')
            ->where('entity_type', $type)
            ->where('old_id', $oldId)
            ->value('new_id');
    }

    public static function has(string $type, string $oldId): bool
    {
        return DB::table('import_id_maps')
            ->where('entity_type', $type)
            ->where('old_id', $oldId)
            ->exists();
    }

    public static function clear(string $type): void
    {
        DB::table('import_id_maps')->where('entity_type', $type)->delete();
    }

    public static function clearAll(): void
    {
        DB::table('import_id_maps')->truncate();
    }
}
