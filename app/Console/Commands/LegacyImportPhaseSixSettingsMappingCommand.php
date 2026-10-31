<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPhaseSixSettingsMappingServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixSettingsMappingResultDTO;
use Illuminate\Console\Command;

final class LegacyImportPhaseSixSettingsMappingCommand extends Command
{
    protected $signature = 'legacy-import:phase6-settings-mapping
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/phase6-settings : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export a read-only Phase 6 legacy settings-to-current-settings mapping report.';

    public function __construct(
        private readonly LegacyPhaseSixSettingsMappingServiceInterface $settingsMappingService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->settingsMappingService->export(
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Phase 6 Settings Mapping Report');
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Safe mapping rows: '.$result->safeMappingRows);
        $this->line('Backlog rows: '.$result->backlogRows);
        $this->line('Duplicate conflict rows: '.$result->duplicateConflictRows);
        $this->line('Unsafe value rows: '.$result->unsafeValueRows);

        if ($result->statusCounts !== []) {
            $this->table(['Status', 'Rows'], collect($result->statusCounts)->map(
                fn (int $count, string $status): array => [$status, (string) $count]
            )->values()->all());
        }

        if ($result->targetCounts !== []) {
            $this->table(['Target', 'Rows'], collect($result->targetCounts)->map(
                fn (int $count, string $target): array => [$target, (string) $count]
            )->values()->all());
        }

        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyPhaseSixSettingsMappingResultDTO $result): array
    {
        return [
            'disk' => $result->disk,
            'scanned_rows' => $result->scannedRows,
            'safe_mapping_rows' => $result->safeMappingRows,
            'backlog_rows' => $result->backlogRows,
            'duplicate_conflict_rows' => $result->duplicateConflictRows,
            'unsafe_value_rows' => $result->unsafeValueRows,
            'status_counts' => $result->statusCounts,
            'target_counts' => $result->targetCounts,
            'paths' => $result->paths,
        ];
    }
}
