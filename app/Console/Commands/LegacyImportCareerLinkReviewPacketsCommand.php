<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyCareerLinkReviewPacketServiceInterface;
use Illuminate\Console\Command;

final class LegacyImportCareerLinkReviewPacketsCommand extends Command
{
    protected $signature = 'legacy-import:career-link-review-packets
        {--disk=local : Private storage disk}
        {--dir=legacy-import-exports/career-link-review-packets : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export private career-link review evidence without writing content.';

    public function __construct(private readonly LegacyCareerLinkReviewPacketServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->export((string) $this->option('disk'), (string) $this->option('dir'));
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(get_object_vars($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Private Legacy Career Link Review Packet');
            $this->line('Total/unblocked: '.$result->totalRows.'/'.$result->candidateRows);
            foreach ($result->paths as $path) {
                $this->line($path);
            }
        }

        return self::SUCCESS;
    }
}
