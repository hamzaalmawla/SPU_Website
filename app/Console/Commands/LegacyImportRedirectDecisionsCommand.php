<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyRedirectDecisionServiceInterface;
use Illuminate\Console\Command;
use Throwable;

final class LegacyImportRedirectDecisionsCommand extends Command
{
    protected $signature = 'legacy-import:redirect-decisions
        {input : Reviewed redirect evidence CSV path}
        {--disk=local : Private storage disk}
        {--batch= : Stable decision batch ID}
        {--write : Persist approved redirects transactionally}
        {--approve= : Required write approval token}';

    protected $description = 'Plan or apply explicitly approved query-aware legacy redirects.';

    public function __construct(
        private readonly LegacyRedirectDecisionServiceInterface $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->decide(
                input: (string) $this->argument('input'),
                disk: (string) $this->option('disk'),
                write: (bool) $this->option('write'),
                approval: is_string($this->option('approve')) ? $this->option('approve') : null,
                batch: is_string($this->option('batch')) ? $this->option('batch') : null,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Legacy Redirect Decisions '.((bool) $this->option('write') ? 'Apply' : 'Dry Run'));
        $this->line('Batch: '.$result->batch);
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Approved rows: '.$result->approvedRows);
        $this->line('Eligible rows: '.$result->eligibleRows);
        $this->line('Created rows: '.$result->createdRows);
        $this->line('Idempotent rows: '.$result->idempotentRows);
        $this->line('Skipped rows: '.$result->skippedRows);

        foreach ($result->reasonCounts as $reason => $count) {
            $this->warn($reason.': '.$count);
        }

        return self::SUCCESS;
    }
}
