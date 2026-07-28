<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPublicStaffReviewPacketServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportPublicStaffReviewPacketsCommand extends Command
{
    protected $signature = 'legacy-import:public-staff-review-packets
        {--service=* : Service type 1-14 (repeatable; defaults to all)}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/public-staff-review-packets : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export private audit evidence packets for jx_councils public staff review.';

    public function __construct(private readonly LegacyPublicStaffReviewPacketServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->export(
                services: (array) $this->option('service'),
                disk: (string) $this->option('disk'),
                directory: (string) $this->option('dir'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $payload = get_object_vars($result);
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Public Staff Review Packets');
        $this->line('Source/output/packets: '.$result->sourceRows.'/'.$result->outputRows.'/'.$result->packetCount);
        $this->line('Hidden/link/orphan/mapped: '.$result->hiddenRows.'/'.$result->linkRows.'/'.$result->orphanRows.'/'.$result->mappedRows);
        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }
        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
