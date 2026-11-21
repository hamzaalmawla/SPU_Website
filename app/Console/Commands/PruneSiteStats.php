<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\SiteStats\SiteStatsRecorderInterface;
use Illuminate\Console\Command;

final class PruneSiteStats extends Command
{
    protected $signature = 'site-stats:prune';

    protected $description = 'Delete visitor hashes and per-page statistics older than their retention (daily totals stay).';

    public function handle(SiteStatsRecorderInterface $recorder): int
    {
        $this->info('Pruned '.$recorder->prune().' visitor statistics rows.');

        return self::SUCCESS;
    }
}
