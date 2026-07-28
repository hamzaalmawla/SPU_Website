<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyMembersReviewPacketServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportMembersReviewPacketsCommand extends Command
{
    protected $signature = 'legacy-import:members-review-packets
        {--service=* : Service type 1 or 2 (repeatable; defaults to both)}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/members-review-packets : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export evidence-only /members/ reconciliation packets without importing or redirecting.';

    public function __construct(private readonly LegacyMembersReviewPacketServiceInterface $service)
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

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(get_object_vars($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Members Reconciliation Packets');
        $this->line('Categories source/output: '.$result->categorySourceRows.'/'.$result->categoryOutputRows);
        $this->line('Items source/output: '.$result->itemSourceRows.'/'.$result->itemOutputRows);
        $this->line('Packets: '.$result->packetCount);
        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }
        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
