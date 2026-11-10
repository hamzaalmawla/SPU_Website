<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyCategoryReviewPacketServiceInterface;
use App\DTOs\Legacy\LegacyCategoryReviewPacketResultDTO;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportCategoryReviewPacketsCommand extends Command
{
    protected $signature = 'legacy-import:category-review-packets
        {--subsite=* : Subsite to audit (root or admin; repeatable)}
        {--service=* : In-scope service type filter (repeatable)}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/category-review-packets : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export read-only editorial review packets for scoped legacy categories.';

    public function __construct(private readonly LegacyCategoryReviewPacketServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->export(
                subsites: (array) $this->option('subsite'),
                services: (array) $this->option('service'),
                disk: (string) $this->option('disk'),
                directory: (string) $this->option('dir'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $payload = $this->payload($result);
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Category Review Packets');
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

    /** @return array<string, mixed> */
    private function payload(LegacyCategoryReviewPacketResultDTO $result): array
    {
        return [
            'disk' => $result->disk, 'selected_subsites' => $result->selectedSubsites,
            'selected_services' => $result->selectedServices, 'source_rows' => $result->sourceRows,
            'output_rows' => $result->outputRows, 'packet_count' => $result->packetCount,
            'hidden_rows' => $result->hiddenRows, 'link_rows' => $result->linkRows,
            'orphan_rows' => $result->orphanRows, 'mapped_rows' => $result->mappedRows,
            'action_counts' => $result->actionCounts, 'semantic_counts' => $result->semanticCounts,
            'subsite_counts' => $result->subsiteCounts, 'service_counts' => $result->serviceCounts,
            'blocker_counts' => $result->blockerCounts, 'paths' => $result->paths, 'warnings' => $result->warnings,
        ];
    }
}
