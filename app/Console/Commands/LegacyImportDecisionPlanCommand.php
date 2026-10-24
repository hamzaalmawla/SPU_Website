<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyDecisionPlanServiceInterface;
use Illuminate\Console\Command;

final class LegacyImportDecisionPlanCommand extends Command
{
    protected $signature = 'legacy-import:decision-plan
        {module : Module to plan, e.g. news or links}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/decision-plans : Export directory}';

    protected $description = 'Generate a machine-readable auto-decision plan from legacy quarantine rows.';

    public function __construct(
        private readonly LegacyDecisionPlanServiceInterface $decisionPlanService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = (string) $this->argument('module');
        $result = $this->decisionPlanService->export(
            module: $module,
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );

        $this->info('Legacy decision plan exported.');
        $this->line('Module: '.$result->module);
        $this->line('Disk: '.$result->disk);
        $this->line('Path: '.$result->path);
        $this->line('Decisions: '.$result->decisionCount);
        $this->line('Manual review: '.$result->manualReviewCount);

        foreach ($result->actionCounts as $action => $count) {
            $this->line($action.': '.$count);
        }

        return self::SUCCESS;
    }
}
