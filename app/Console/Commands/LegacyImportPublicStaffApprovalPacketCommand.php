<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPublicStaffApprovalPacketServiceInterface;
use Illuminate\Console\Command;
use Throwable;

final class LegacyImportPublicStaffApprovalPacketCommand extends Command
{
    protected $signature = 'legacy-import:public-staff-approval-packet {input*} {--approved-by=} {--disk=local} {--dir=legacy-import-exports/public-staff-approval-packets} {--central}';

    protected $description = 'Build a conservative approved subset for disabled faculty staff imports.';

    public function __construct(private readonly LegacyPublicStaffApprovalPacketServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->build(
                array_map('strval', $this->argument('input')),
                (string) $this->option('approved-by'),
                (string) $this->option('disk'),
                (string) $this->option('dir'),
                (bool) $this->option('central'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Public Staff Approval Packet');
        $this->line('Scanned/approved/rejected: '.$result->scannedRows.'/'.$result->approvedRows.'/'.$result->rejectedRows);
        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        return self::SUCCESS;
    }
}
