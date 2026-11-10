<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyRedirectDecisionServiceInterface;
use Illuminate\Console\Command;
use Throwable;

final class LegacyImportRedirectRollbackCommand extends Command
{
    protected $signature = 'legacy-import:redirect-rollback
        {batch : Applied redirect decision batch ID}
        {--write : Delete redirects created by this batch}
        {--approve= : Required rollback approval token}';

    protected $description = 'Preview or roll back one applied legacy redirect decision batch.';

    public function __construct(
        private readonly LegacyRedirectDecisionServiceInterface $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->rollback(
                batch: (string) $this->argument('batch'),
                write: (bool) $this->option('write'),
                approval: is_string($this->option('approve')) ? $this->option('approve') : null,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Legacy Redirect Rollback '.($result->wroteChanges ? 'Apply' : 'Preview'));
        $this->line('Batch: '.$result->batch);
        $this->line('Batch redirects: '.$result->eligibleRows);
        $this->line('Deleted redirects: '.$result->createdRows);

        foreach ($result->reasonCounts as $reason => $count) {
            $this->warn($reason.': '.$count);
        }

        return self::SUCCESS;
    }
}
