<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Media\MediaServiceInterface;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ImportPublicMediaAssetsCommand extends Command
{
    protected $signature = 'media:import-public-assets {--dir=* : Public directory to scan, relative to public/. Defaults to images and documents.} {--user-id= : Optional user id for audit attribution.}';

    protected $description = 'Import existing public assets into the media library without moving or deleting files.';

    public function handle(MediaServiceInterface $mediaService): int
    {
        $directories = $this->option('dir');
        $directories = is_array($directories) && $directories !== [] ? $directories : ['images', 'documents', 'docs', 'files'];
        $userIdOption = $this->option('user-id');
        $userId = is_numeric($userIdOption) ? (int) $userIdOption : null;
        $imported = 0;
        $skipped = 0;

        foreach ($directories as $directory) {
            $relativeDirectory = trim(str_replace('\\', '/', (string) $directory), '/');
            $fullDirectory = public_path($relativeDirectory);

            if ($relativeDirectory === '' || ! is_dir($fullDirectory)) {
                $skipped++;
                $this->warn("Skipped missing public directory: {$relativeDirectory}");

                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullDirectory));

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $relativePath = $relativeDirectory.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($fullDirectory) + 1));
                $result = $mediaService->importPublicAsset($relativePath, $userId);

                if ($result === null) {
                    $skipped++;

                    continue;
                }

                $imported++;
            }
        }

        $this->info("Imported or reused {$imported} media assets. Skipped {$skipped} entries.");

        return self::SUCCESS;
    }
}
