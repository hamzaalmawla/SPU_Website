<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\SiteStats\SiteStatsRecorderInterface;
use Illuminate\Console\Command;

final class CollectSiteStats extends Command
{
    protected $signature = 'site-stats:collect';

    protected $description = 'Write the page views public/stats-hit.php queued into the dashboard\'s visitor statistics.';

    public function handle(SiteStatsRecorderInterface $recorder): int
    {
        $this->info('Recorded '.$recorder->collectQueued().' page views.');

        return self::SUCCESS;
    }
}
