<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyFileContinuityProbeServiceInterface;
use App\DTOs\Legacy\LegacyFileContinuityProbeResultDTO;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

final class LegacyFileContinuityProbeService implements LegacyFileContinuityProbeServiceInterface
{
    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh',
        'asp', 'aspx', 'jsp', 'exe', 'dll', 'com', 'bat', 'cmd', 'ps1',
        'env', 'sql', 'bak', 'conf', 'config', 'ini', 'log',
    ];

    /** @var list<string> */
    private const ACTIVE_REVIEW_EXTENSIONS = ['htm', 'html', 'shtml', 'svg', 'js', 'mjs', 'xml', 'xsl'];

    /** @var list<string> */
    private const SAFE_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'ico',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        'txt', 'csv', 'rtf', 'zip', 'rar', '7z', 'gz', 'tar', 'mp3', 'mp4', 'webm', 'wav',
    ];

    public function probe(
        string $root,
        bool $computeChecksums = true,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/file-continuity-probes',
        ?string $targetRoot = null,
    ): LegacyFileContinuityProbeResultDTO {
        $realRoot = realpath($root);

        if ($realRoot === false || ! is_dir($realRoot) || ! is_readable($realRoot)) {
            throw new \InvalidArgumentException('Legacy public root must be an existing readable directory.');
        }

        $realTargetRoot = null;

        if ($targetRoot !== null) {
            $realTargetRoot = realpath($targetRoot);

            if ($realTargetRoot === false || ! is_dir($realTargetRoot) || ! is_readable($realTargetRoot)) {
                throw new \InvalidArgumentException('Laravel target root must be an existing readable directory.');
            }
        }

        $configuredPaths = $this->configuredPaths();
        $existingDirectories = [];
        $missingDirectories = [];

        foreach ($configuredPaths as $relativeDirectory) {
            $fullDirectory = $realRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

            if (is_dir($fullDirectory) && is_readable($fullDirectory)) {
                $existingDirectories[$relativeDirectory] = $fullDirectory;
            } else {
                $missingDirectories[] = '/'.$relativeDirectory.'/';
            }
        }

        $scanDirectories = $this->removeNestedDirectories($existingDirectories);
        $rows = [];
        $warnings = [];
        $caseIndex = [];
        $caseCollisions = [];

        foreach ($scanDirectories as $fullDirectory) {
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fullDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
                );

                foreach ($iterator as $item) {
                    if (! $item instanceof SplFileInfo || (! $item->isFile() && ! $item->isLink())) {
                        continue;
                    }

                    $row = $this->inspect($realRoot, $item, $computeChecksums, $realTargetRoot ?: null);
                    $rows[] = $row;
                    $caseKey = mb_strtolower($row['legacy_path']);
                    $caseIndex[$caseKey][$row['legacy_path']] = true;
                }
            } catch (Throwable $exception) {
                $warnings[] = 'Could not scan an approved static directory: '.$exception->getMessage();
            }
        }

        foreach ($caseIndex as $paths) {
            if (count($paths) > 1) {
                $caseCollisions[] = implode(' | ', array_keys($paths));
            }
        }

        usort($rows, static fn (array $left, array $right): int => strcmp($left['legacy_path'], $right['legacy_path']));
        $stamp = now()->format('Ymd_His');
        $directory = trim($directory, '/') ?: 'legacy-import-exports/file-continuity-probes';
        $basePath = $directory.'/'.$stamp.'_legacy_static_files';
        $paths = [$basePath.'.csv', $basePath.'.json'];
        $classificationCounts = array_count_values(array_column($rows, 'classification'));
        $symlinkEscapes = count(array_filter($rows, static fn (array $row): bool => $row['symlink_escape'] === 1));
        $targetCollisions = count(array_filter($rows, static fn (array $row): bool => $row['target_exists'] === 1));
        $differingTargetCollisions = count(array_filter($rows, static fn (array $row): bool => $row['target_collision'] === 'different'));
        $rootFingerprint = hash('sha256', str_replace('\\', '/', $realRoot));

        Storage::disk($disk)->put($paths[0], $this->csv($rows));
        Storage::disk($disk)->put($paths[1], (string) json_encode([
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'root_fingerprint_sha256' => $rootFingerprint,
            'absolute_root_recorded' => false,
            'target_root_fingerprint_sha256' => $realTargetRoot !== null ? hash('sha256', str_replace('\\', '/', $realTargetRoot)) : null,
            'absolute_target_root_recorded' => false,
            'checksums_computed' => $computeChecksums,
            'configured_static_directories' => array_map(static fn (string $path): string => '/'.$path.'/', $configuredPaths),
            'scanned_directories' => count($scanDirectories),
            'missing_directories' => $missingDirectories,
            'summary' => [
                'files' => count($rows),
                'safe_static' => $classificationCounts['safe_static'] ?? 0,
                'manual_review' => $classificationCounts['manual_review'] ?? 0,
                'blocked_executable_or_sensitive' => $classificationCounts['blocked_executable_or_sensitive'] ?? 0,
                'symlink_escapes' => $symlinkEscapes,
                'case_collision_groups' => count($caseCollisions),
                'target_path_collisions' => $targetCollisions,
                'differing_target_collisions' => $differingTargetCollisions,
            ],
            'case_collisions' => $caseCollisions,
            'warnings' => array_values(array_unique($warnings)),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return new LegacyFileContinuityProbeResultDTO(
            rootFingerprint: $rootFingerprint,
            scannedDirectories: count($scanDirectories),
            fileCount: count($rows),
            safeFiles: $classificationCounts['safe_static'] ?? 0,
            reviewFiles: $classificationCounts['manual_review'] ?? 0,
            blockedFiles: $classificationCounts['blocked_executable_or_sensitive'] ?? 0,
            symlinkEscapes: $symlinkEscapes,
            caseCollisions: count($caseCollisions),
            targetCollisions: $targetCollisions,
            differingTargetCollisions: $differingTargetCollisions,
            missingDirectories: $missingDirectories,
            paths: $paths,
            warnings: array_values(array_unique($warnings)),
        );
    }

    /** @return list<string> */
    private function configuredPaths(): array
    {
        $paths = config('old_database.file_continuity_static_directories', []);

        if (! is_array($paths)) {
            return [];
        }

        return collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path) && $this->isSafeRelativeDirectory($path))
            ->map(static fn (string $path): string => trim(str_replace('\\', '/', $path), '/'))
            ->unique()
            ->values()
            ->all();
    }

    private function isSafeRelativeDirectory(string $path): bool
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return $path !== '' && ! str_contains($path, '..') && ! str_contains($path, "\0");
    }

    /** @param array<string, string> $directories @return array<string, string> */
    private function removeNestedDirectories(array $directories): array
    {
        uksort($directories, static fn (string $left, string $right): int => strlen($left) <=> strlen($right));
        $result = [];

        foreach ($directories as $relative => $full) {
            $nested = false;

            foreach (array_keys($result) as $parent) {
                if (str_starts_with($relative.'/', $parent.'/')) {
                    $nested = true;
                    break;
                }
            }

            if (! $nested) {
                $result[$relative] = $full;
            }
        }

        return $result;
    }

    /** @return array<string, int|string|null> */
    private function inspect(string $root, SplFileInfo $item, bool $computeChecksum, ?string $targetRoot): array
    {
        $pathname = $item->getPathname();
        $relative = ltrim(str_replace('\\', '/', substr($pathname, strlen($root))), '/');
        $legacyPath = '/'.$relative;
        $extension = mb_strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        $basename = mb_strtolower(basename($relative));
        $resolved = realpath($pathname);
        $symlinkEscape = $item->isLink() && ($resolved === false || ! $this->isWithinRoot($root, $resolved));
        $classification = $this->classification($basename, $extension, $symlinkEscape);
        $mimeType = null;
        $checksum = null;
        $size = null;
        $error = null;

        if (! $symlinkEscape && $resolved !== false) {
            try {
                $mimeType = File::mimeType($resolved) ?: 'application/octet-stream';
                $fileSize = filesize($resolved);
                $size = $fileSize !== false ? (int) $fileSize : null;
                $checksumValue = $computeChecksum ? hash_file('sha256', $resolved) : null;
                $checksum = $checksumValue !== false ? $checksumValue : null;
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        [$targetExists, $targetChecksum, $targetCollision] = $this->targetInspection(
            $targetRoot,
            $relative,
            $computeChecksum,
            $checksum,
        );

        return [
            'legacy_path' => $legacyPath,
            'encoded_url_path' => $this->encodedPath($legacyPath),
            'extension' => $extension !== '' ? $extension : null,
            'mime_type' => $mimeType,
            'file_size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'is_symlink' => $item->isLink() ? 1 : 0,
            'symlink_escape' => $symlinkEscape ? 1 : 0,
            'classification' => $classification,
            'target_exists' => $targetExists ? 1 : 0,
            'target_checksum_sha256' => $targetChecksum,
            'target_collision' => $targetCollision,
            'inspection_error' => $error,
        ];
    }

    /** @return array{0: bool, 1: ?string, 2: string} */
    private function targetInspection(?string $targetRoot, string $relative, bool $computeChecksum, ?string $sourceChecksum): array
    {
        if ($targetRoot === null) {
            return [false, null, 'not_checked'];
        }

        $candidate = $targetRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($candidate);

        if ($resolved === false || ! is_file($resolved) || ! $this->isWithinRoot($targetRoot, $resolved)) {
            return [false, null, 'none'];
        }

        if (! $computeChecksum || $sourceChecksum === null) {
            return [true, null, 'uncompared'];
        }

        try {
            $targetChecksum = hash_file('sha256', $resolved);
            $targetChecksum = $targetChecksum !== false ? $targetChecksum : null;
        } catch (Throwable) {
            $targetChecksum = null;
        }

        return [
            true,
            $targetChecksum,
            $targetChecksum !== null && hash_equals($sourceChecksum, $targetChecksum) ? 'identical' : 'different',
        ];
    }

    private function classification(string $basename, string $extension, bool $symlinkEscape): string
    {
        if ($symlinkEscape || str_starts_with($basename, '.ht') || $this->hasBlockedExtensionComponent($basename)) {
            return 'blocked_executable_or_sensitive';
        }

        if (in_array($extension, self::ACTIVE_REVIEW_EXTENSIONS, true) || ! in_array($extension, self::SAFE_EXTENSIONS, true)) {
            return 'manual_review';
        }

        return 'safe_static';
    }

    private function hasBlockedExtensionComponent(string $basename): bool
    {
        $components = array_map('mb_strtolower', explode('.', $basename));

        return array_intersect($components, self::BLOCKED_EXTENSIONS) !== [];
    }

    private function isWithinRoot(string $root, string $path): bool
    {
        $root = mb_strtolower(rtrim(str_replace('\\', '/', $root), '/')).'/';
        $path = mb_strtolower(str_replace('\\', '/', $path));

        return str_starts_with($path, $root);
    }

    private function encodedPath(string $path): string
    {
        return '/'.implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
    }

    /** @param list<array<string, int|string|null>> $rows */
    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Unable to create the file continuity CSV stream.');
        }

        $headers = ['legacy_path', 'encoded_url_path', 'extension', 'mime_type', 'file_size_bytes', 'checksum_sha256', 'is_symlink', 'symlink_escape', 'classification', 'target_exists', 'target_checksum_sha256', 'target_collision', 'inspection_error'];
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(static fn (string $header): string => (string) ($row[$header] ?? ''), $headers));
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return is_string($contents) ? $contents : '';
    }
}
