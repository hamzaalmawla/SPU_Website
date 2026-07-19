<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyFileInventoryServiceInterface;
use App\DTOs\Legacy\LegacyFileInventoryScanResultDTO;
use App\Models\Legacy\LegacyFileInventory;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

final class LegacyFileInventoryService implements LegacyFileInventoryServiceInterface
{
    /** @var array<string, array<string, string>>|null */
    private ?array $downloadFileIndex = null;

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
    ) {}

    public function scan(bool $write, ?int $limit = null, ?callable $progress = null): LegacyFileInventoryScanResultDTO
    {
        $references = [];
        $warnings = [];
        $missingTables = 0;
        $missingColumns = 0;
        $writtenRows = 0;
        $updatedRows = 0;
        $existingFiles = 0;
        $missingFiles = 0;
        $sampleMissingPaths = [];
        $checksumFailedFiles = 0;
        $checksumFailedPaths = [];
        $unexpectedErrorFiles = 0;
        $unexpectedErrorPaths = [];
        $brokenSymlinks = 0;
        $brokenSymlinkPaths = [];

        foreach ($this->configuredFields() as $definition) {
            $table = $definition['table'];
            $idColumn = $definition['id_column'];

            if (! $this->legacyTableExists($table)) {
                $missingTables++;
                $warnings[] = "Missing legacy table [{$table}].";

                continue;
            }

            if (! $this->legacyColumnExists($table, $idColumn)) {
                $missingColumns++;
                $warnings[] = "Missing legacy ID column [{$table}.{$idColumn}].";

                continue;
            }

            foreach ($definition['columns'] as $column) {
                if (! $this->legacyColumnExists($table, $column)) {
                    $missingColumns++;
                    $warnings[] = "Missing legacy file column [{$table}.{$column}].";

                    continue;
                }

                foreach ($this->scanColumn($table, $idColumn, $column, $limit) as $reference) {
                    $references[] = $reference;
                }
            }
        }

        $grouped = collect($references)->groupBy('legacy_path');

        $existingByPath = $write
            ? LegacyFileInventory::query()
                ->get()
                ->keyBy(fn (LegacyFileInventory $inventory): string => mb_strtolower($inventory->legacy_path))
            : collect();

        $processedPaths = 0;
        $totalPaths = $grouped->count();

        foreach ($grouped as $legacyPath => $pathReferences) {
            $first = $pathReferences->first();
            $existing = $write ? $existingByPath->get(mb_strtolower((string) $legacyPath)) : null;
            $inspection = $this->inspectFile((string) $legacyPath, false, $pathReferences->values()->all());
            $status = $this->statusForInspection($existing instanceof LegacyFileInventory ? $existing : null, $inspection['exists']);

            if ($inspection['exists']) {
                $existingFiles++;
            } else {
                $missingFiles++;

                if (count($sampleMissingPaths) < 20) {
                    $sampleMissingPaths[] = (string) $legacyPath;
                }
            }

            if ($inspection['checksum_failed']) {
                $checksumFailedFiles++;

                if (count($checksumFailedPaths) < 20) {
                    $checksumFailedPaths[] = (string) $legacyPath;
                }
            }

            if ($inspection['error'] !== null) {
                $unexpectedErrorFiles++;

                if (count($unexpectedErrorPaths) < 20) {
                    $unexpectedErrorPaths[] = (string) $legacyPath.' :: '.$inspection['error'];
                }
            }

            if ($inspection['broken_symlink']) {
                $brokenSymlinks++;

                if (count($brokenSymlinkPaths) < 20) {
                    $brokenSymlinkPaths[] = (string) $legacyPath;
                }
            }

            if ($write) {
                $payload = [
                    'legacy_path' => (string) $legacyPath,
                    'source_table' => $first['source_table'],
                    'source_column' => $first['source_column'],
                    'source_id' => $first['source_id'],
                    'status' => $status,
                    'extension' => $this->extensionForPath((string) $legacyPath),
                    'mime_type' => $inspection['mime_type'],
                    'file_size_bytes' => $inspection['file_size_bytes'],
                    'checksum_sha256' => null,
                    'checksum_status' => 'pending',
                    'reference_count' => $pathReferences->count(),
                    'source_references' => $pathReferences->values()->all(),
                    'last_seen_at' => now(),
                    'notes' => $this->inspectionNotes($inspection),
                ];

                if ($existing instanceof LegacyFileInventory) {
                    $existing->forceFill($payload)->save();
                    $updatedRows++;
                } else {
                    $existingByPath->put(mb_strtolower((string) $legacyPath), LegacyFileInventory::query()->create($payload));
                    $writtenRows++;
                }
            }

            $processedPaths++;

            if ($progress !== null && ($processedPaths % 250 === 0 || $processedPaths === $totalPaths)) {
                $progress($processedPaths, $totalPaths, $existingFiles, $missingFiles, $writtenRows, $updatedRows);
            }
        }

        return new LegacyFileInventoryScanResultDTO(
            wroteChanges: $write,
            scannedReferences: count($references),
            uniqueLegacyPaths: $grouped->count(),
            writtenRows: $writtenRows,
            updatedRows: $updatedRows,
            missingTables: $missingTables,
            missingColumns: $missingColumns,
            existingFiles: $existingFiles,
            missingFiles: $missingFiles,
            warnings: array_values(array_unique($warnings)),
            sampleMissingPaths: $sampleMissingPaths,
            checksumFailedFiles: $checksumFailedFiles,
            checksumFailedPaths: $checksumFailedPaths,
            unexpectedErrorFiles: $unexpectedErrorFiles,
            unexpectedErrorPaths: $unexpectedErrorPaths,
            brokenSymlinks: $brokenSymlinks,
            brokenSymlinkPaths: $brokenSymlinkPaths,
        );
    }

    /**
     * @return array<int, array{table: string, id_column: string, columns: array<int, string>}>
     */
    private function configuredFields(): array
    {
        $fields = config('old_database.file_inventory_fields', []);

        if (! is_array($fields)) {
            return [];
        }

        return collect($fields)
            ->map(function (mixed $definition): ?array {
                if (! is_array($definition) || ! is_string($definition['table'] ?? null)) {
                    return null;
                }

                $columns = $definition['columns'] ?? [];

                return [
                    'table' => $definition['table'],
                    'id_column' => is_string($definition['id_column'] ?? null) ? $definition['id_column'] : 'id',
                    'columns' => is_array($columns) ? array_values(array_filter($columns, 'is_string')) : [],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function legacyTableExists(string $table): bool
    {
        try {
            return $this->oldDatabase->schema()->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function legacyColumnExists(string $table, string $column): bool
    {
        try {
            return $this->oldDatabase->schema()->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array{legacy_path: string, source_table: string, source_column: string, source_id: ?int}>
     */
    private function scanColumn(string $table, string $idColumn, string $column, ?int $limit): array
    {
        $query = $this->oldDatabase->table($table)
            ->select([$idColumn, $column])
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->orderBy($idColumn);

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(function (object $row) use ($table, $idColumn, $column): ?array {
                $path = $this->normalizeLegacyPath($row->{$column} ?? null);

                if ($path === null) {
                    return null;
                }

                $sourceId = $row->{$idColumn} ?? null;

                return [
                    'legacy_path' => $path,
                    'source_table' => $table,
                    'source_column' => $column,
                    'source_id' => is_numeric($sourceId) ? (int) $sourceId : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeLegacyPath(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $path = trim((string) $value);

        if ($path === '' || Str::startsWith(mb_strtolower($path), ['http://', 'https://', 'mailto:', 'javascript:'])) {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = preg_replace('#^(?:\.\./|\./)+#', '', $path) ?? $path;
        $path = strtok($path, '?#') ?: $path;
        $path = ltrim($path, '/');

        if ($path === '') {
            return null;
        }

        return '/'.$path;
    }

    private function extensionForPath(string $path): ?string
    {
        $extension = mb_strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        return $extension !== '' ? mb_substr($extension, 0, 32) : null;
    }

    /** @param array<int, array{legacy_path: string, source_table: string, source_column: string, source_id: ?int}> $references @return array{exists: bool, full_path: ?string, mime_type: ?string, file_size_bytes: ?int, checksum_sha256: ?string, checksum_failed: bool, error: ?string, broken_symlink: bool} */
    private function inspectFile(string $legacyPath, bool $computeChecksum, array $references): array
    {
        $fullPath = $this->findFullPath($legacyPath, $references);

        if ($fullPath === null) {
            return [
                'exists' => false,
                'full_path' => null,
                'mime_type' => null,
                'file_size_bytes' => null,
                'checksum_sha256' => null,
                'checksum_failed' => false,
                'error' => null,
                'broken_symlink' => $this->hasBrokenSymlinkCandidate($legacyPath, $references),
            ];
        }

        $mimeType = null;
        $fileSize = null;
        $checksum = null;
        $checksumFailed = false;
        $error = null;

        if ($computeChecksum) {
            try {
                $mimeType = File::mimeType($fullPath) ?: 'application/octet-stream';
                $fileSizeValue = filesize($fullPath);
                $fileSize = $fileSizeValue !== false ? (int) $fileSizeValue : null;
            } catch (Throwable $throwable) {
                $error = $throwable->getMessage();
            }

            try {
                $checksumValue = hash_file('sha256', $fullPath);
                $checksum = $checksumValue !== false ? $checksumValue : null;
                $checksumFailed = $checksum === null;
            } catch (Throwable $throwable) {
                $checksumFailed = true;
                $error = $error !== null ? $error.' | checksum: '.$throwable->getMessage() : 'checksum: '.$throwable->getMessage();
            }
        }

        return [
            'exists' => true,
            'full_path' => $fullPath,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSize,
            'checksum_sha256' => $checksum,
            'checksum_failed' => $checksumFailed,
            'error' => $error,
            'broken_symlink' => false,
        ];
    }

    /** @param array<int, array{legacy_path: string, source_table: string, source_column: string, source_id: ?int}> $references */
    private function findFullPath(string $legacyPath, array $references): ?string
    {
        foreach ($this->fileInventoryRoots() as $root) {
            foreach ($this->candidateRelativePaths($legacyPath, $references) as $relativePath) {
                $candidate = rtrim($root, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                try {
                    if (File::isFile($candidate)) {
                        return $candidate;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /** @param array<int, array{legacy_path: string, source_table: string, source_column: string, source_id: ?int}> $references @return list<string> */
    private function candidateRelativePaths(string $legacyPath, array $references): array
    {
        $relativePath = ltrim(str_replace('\\', '/', $legacyPath), '/');

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return [];
        }

        $paths = [$relativePath];

        if (! str_contains($relativePath, '/')) {
            if ($this->hasCvReference($references)) {
                $paths[] = 'cv_bank/'.$relativePath;
            }

            $paths[] = 'downloads/files/'.$relativePath;
            $paths[] = 'downloads/files2/'.$relativePath;

            $alternative = $this->alternativeDownloadRelativePath($relativePath);

            if ($alternative !== null) {
                $paths[] = $alternative;
            }
        }

        return array_values(array_unique($paths));
    }

    /** @param array<int, array{legacy_path: string, source_table: string, source_column: string, source_id: ?int}> $references */
    private function hasCvReference(array $references): bool
    {
        foreach ($references as $reference) {
            $table = $reference['source_table'] ?? null;
            $column = $reference['source_column'] ?? null;

            if (($table === 'jx_councils' && in_array($column, ['cv', 'ar_cv'], true)) || ($table === 'jx_councils1' && $column === 'cv')) {
                return true;
            }
        }

        return false;
    }

    private function alternativeDownloadRelativePath(string $filename): ?string
    {
        if (! preg_match('/^\d+_(.+)$/', $filename, $matches)) {
            return null;
        }

        $suffix = mb_strtolower($matches[1]);
        $matches = [];

        foreach ($this->downloadFileIndex() as $files) {
            foreach ($files as $lowerFilename => $relativePath) {
                if (str_ends_with($lowerFilename, $suffix)) {
                    $matches[$relativePath] = true;
                }
            }
        }

        return count($matches) === 1 ? array_key_first($matches) : null;
    }

    /** @return array<string, array<string, string>> */
    private function downloadFileIndex(): array
    {
        if ($this->downloadFileIndex !== null) {
            return $this->downloadFileIndex;
        }

        $index = [];

        foreach ($this->fileInventoryRoots() as $root) {
            foreach (['downloads/files', 'downloads/files2'] as $directory) {
                $fullDirectory = rtrim($root, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);
                $items = @scandir($fullDirectory);

                if ($items === false) {
                    continue;
                }

                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    $fullPath = $fullDirectory.DIRECTORY_SEPARATOR.$item;

                    if (@is_file($fullPath)) {
                        $index[$directory][mb_strtolower($item)] = $directory.'/'.$item;
                    }
                }
            }
        }

        return $this->downloadFileIndex = $index;
    }

    /** @param array<int, array{legacy_path: string, source_table: string, source_column: string, source_id: ?int}> $references */
    private function hasBrokenSymlinkCandidate(string $legacyPath, array $references): bool
    {
        foreach ($this->fileInventoryRoots() as $root) {
            foreach ($this->candidateRelativePaths($legacyPath, $references) as $relativePath) {
                $candidate = rtrim($root, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                if (@is_link($candidate) && ! @file_exists($candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array{exists: bool, error: ?string, broken_symlink: bool} $inspection */
    private function inspectionNotes(array $inspection): ?string
    {
        if ($inspection['error'] !== null) {
            return 'Unexpected legacy file inspection error: '.$inspection['error'];
        }

        if ($inspection['broken_symlink']) {
            return 'Referenced legacy file appears to be a broken symlink in configured inventory roots.';
        }

        return $inspection['exists'] ? null : 'Referenced legacy file was not found in configured inventory roots.';
    }

    /** @return array<int, string> */
    private function fileInventoryRoots(): array
    {
        $roots = config('old_database.file_inventory_roots', [public_path()]);

        if (! is_array($roots)) {
            return [public_path()];
        }

        return collect($roots)
            ->filter(fn (mixed $root): bool => is_string($root) && trim($root) !== '')
            ->map(fn (string $root): string => rtrim($root, DIRECTORY_SEPARATOR.'/\\'))
            ->unique()
            ->values()
            ->all();
    }

    private function statusForInspection(?LegacyFileInventory $existing, bool $exists): string
    {
        if ($existing instanceof LegacyFileInventory && $existing->status === 'mapped') {
            return 'mapped';
        }

        return $exists ? 'unmapped' : 'missing';
    }
}
