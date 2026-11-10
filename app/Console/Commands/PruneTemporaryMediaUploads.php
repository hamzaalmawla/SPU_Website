<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Media\MediaServiceInterface;
use Illuminate\Console\Command;

final class PruneTemporaryMediaUploads extends Command
{
    protected $signature = 'media:prune-temp {--hours=24 : Remove temp uploads older than this} {--dry-run : Count without deleting}';

    protected $description = 'Delete abandoned admin upload files from media-tmp/.';

    public function handle(MediaServiceInterface $media): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $count = $media->pruneTemporaryUploads($hours, $dryRun);

        $this->info($dryRun
            ? "{$count} temporary upload(s) older than {$hours}h would be removed."
            : "Removed {$count} temporary upload(s) older than {$hours}h.");

        return self::SUCCESS;
    }
}
