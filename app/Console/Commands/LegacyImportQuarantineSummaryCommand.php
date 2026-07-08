<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyQuarantineSummaryServiceInterface;
use Illuminate\Console\Command;

final class LegacyImportQuarantineSummaryCommand extends Command
{
    protected $signature = 'legacy-import:quarantine-summary
        {module? : Optional module filter}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/quarantine-summary : Export directory}';

    protected $description = 'Export human-friendly grouped quarantine review summaries.';

    public function __construct(
        private readonly LegacyQuarantineSummaryServiceInterface $summaryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $result = $this->summaryService->export(
            module: is_string($module) ? $module : null,
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );

        $this->info('Legacy quarantine summary export created.');
        $this->line('Disk: '.$result->disk);
        $this->line('Rows: '.$result->rowCount);
        $this->line('Grouped issues: '.$result->summaryGroupCount);
        $this->line('Decision groups: '.$result->needsDecisionGroupCount);

        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        return self::SUCCESS;
    }
}
