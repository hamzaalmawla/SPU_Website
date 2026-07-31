<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyNewsApprovalPacketServiceInterface;
use Illuminate\Console\Command;
use Throwable;

final class LegacyImportNewsApprovalPacketCommand extends Command
{
    protected $signature = 'legacy-import:news-approval-packet
        {input* : Root service 3 and 4 category review packet paths}
        {--approved-by= : Required approval identity}
        {--allow-arabic-fallback : Accept visible records whose only translation blocker is an English placeholder}
        {--disk=local : Private storage disk}
        {--dir=legacy-import-exports/news-approval-packets : Private output directory}';

    protected $description = 'Build a conservative approved subset for disabled legacy news and announcement imports.';

    public function __construct(
        private readonly LegacyNewsApprovalPacketServiceInterface $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->build(
                inputs: array_map('strval', $this->argument('input')),
                approvedBy: is_string($this->option('approved-by')) ? $this->option('approved-by') : '',
                disk: (string) $this->option('disk'),
                directory: (string) $this->option('dir'),
                allowArabicFallback: (bool) $this->option('allow-arabic-fallback'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Legacy News Approval Packet');
        $this->line('Scanned/approved/rejected: '.$result->scannedRows.'/'.$result->approvedRows.'/'.$result->rejectedRows);

        foreach ($result->serviceCounts as $service => $count) {
            $this->line('Service '.$service.': '.$count.' approved');
        }

        foreach ($result->rejectionCounts as $reason => $count) {
            $this->line($reason.': '.$count);
        }

        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        return self::SUCCESS;
    }
}
