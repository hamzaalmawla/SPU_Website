<?php

declare(strict_types=1);

namespace App\Contracts\Search;

/**
 * Defines maintenance of the derived public-search index.
 *
 * The index is derived data: rebuilding it from scratch must always produce the
 * same rows as incremental updates, so every method here is idempotent.
 */
interface SearchIndexServiceInterface
{
    /**
     * Content sources that feed the index, keyed by the stable source key used
     * by the backfill command and the sync hooks.
     */
    public const SOURCES = [
        'news',
        'research',
        'pages',
        'faculty-members',
        'persons',
        'faculties',
        'faculty-pages',
    ];

    /**
     * Rebuild the index for one source, or for every source when null.
     *
     * Records that are no longer public are removed. Returns the number of
     * index documents written.
     */
    public function rebuild(?string $source = null, bool $fresh = false): int;

    /**
     * Re-index a single source record, removing it when it is no longer public.
     */
    public function syncRecord(string $source, int $recordId): bool;

    /**
     * Drop every index document for a single source record.
     */
    public function forgetRecord(string $source, int $recordId): bool;

    /**
     * Whether the index table exists yet. Sync hooks must no-op before the
     * migration has run.
     */
    public function isAvailable(): bool;
}
