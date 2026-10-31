<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyFaqApprovalPacketServiceInterface;
use Illuminate\Console\Command;
use Throwable;

final class LegacyImportFaqApprovalPacketCommand extends Command
{
    protected $signature = 'legacy-import:faq-approval-packet {input} {--approved-by=} {--disk=local} {--dir=legacy-import-exports/faq-approval-packets}';

    protected $description = 'Build a content- and PII-minimized approved FAQ subset.';

    public function __construct(private readonly LegacyFaqApprovalPacketServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->build((string) $this->argument('input'), (string) $this->option('approved-by'), (string) $this->option('disk'), (string) $this->option('dir'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Legacy FAQ Approval Packet');
        $this->line('Scanned/approved/rejected: '.$result->scannedRows.'/'.$result->approvedRows.'/'.$result->rejectedRows);
        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        return self::SUCCESS;
    }
}
