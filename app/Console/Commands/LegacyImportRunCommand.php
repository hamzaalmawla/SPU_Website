<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyImportRunnerServiceInterface;
use Illuminate\Console\Command;

final class LegacyImportRunCommand extends Command
{
    protected $signature = 'legacy-import:run
        {module : Configured legacy import module}
        {--batch= : Required explicit batch name for future real runs}
        {--dry-run : Record a dry-run batch instead of a blocked run attempt}';

    protected $description = 'Guarded legacy import run entrypoint. Real imports are intentionally not enabled yet.';

    public function __construct(
        private readonly LegacyImportRunnerServiceInterface $runnerService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = (string) $this->argument('module');
        $batchName = $this->option('batch');
        $batchName = is_string($batchName) && $batchName !== '' ? $batchName : null;
        $result = $this->runnerService->run($module, $batchName, (bool) $this->option('dry-run'));

        if ($result->mode === 'dry_run') {
            $this->info($result->message.' '.$result->batch->batchName.' ['.$result->batch->status.']');
            $this->line('Status: '.$result->dryRun->status);
            $this->line('Can run: '.($result->dryRun->canRun ? 'yes' : 'no'));

            return $result->exitCode;
        }

        $this->error($result->message);
        $this->warn('Blocked run batch recorded: '.$result->batch->batchName);

        return $result->exitCode;
    }
}
