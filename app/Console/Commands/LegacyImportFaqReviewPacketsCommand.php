<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyFaqReviewPacketServiceInterface;
use Illuminate\Console\Command;

final class LegacyImportFaqReviewPacketsCommand extends Command
{
    protected $signature = 'legacy-import:faq-review-packets
        {--disk=local : Private storage disk}
        {--dir=legacy-import-exports/faq-review-packets : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export private, PII-free FAQ editorial review packets without writing content.';

    public function __construct(private readonly LegacyFaqReviewPacketServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->export((string) $this->option('disk'), (string) $this->option('dir'));
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(get_object_vars($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Private Legacy FAQ Review Packet');
            $this->line('Total/candidate/backlog: '.$result->totalRows.'/'.$result->candidateRows.'/'.$result->backlogRows);
            foreach ($result->paths as $path) {
                $this->line($path);
            }
        }

        return self::SUCCESS;
    }
}
