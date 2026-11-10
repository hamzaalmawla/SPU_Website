<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyUrlContinuityTriageServiceInterface;
use App\DTOs\Legacy\LegacyUrlContinuityTriageResultDTO;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportUrlContinuityTriageCommand extends Command
{
    protected $signature = 'legacy-import:url-continuity-triage
        {path : URL continuity inventory CSV path on the selected disk}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/url-continuity-triage : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Triage unresolved Phase 5 URL continuity inventory rows without creating redirects.';

    public function __construct(
        private readonly LegacyUrlContinuityTriageServiceInterface $triageService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->triageService->export(
                path: (string) $this->argument('path'),
                disk: (string) $this->option('disk'),
                directory: (string) $this->option('dir'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy URL Continuity Triage');
        $this->line('Source: '.$result->sourcePath);
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Unresolved rows: '.$result->unresolvedRows);
        $this->line('Resolver candidates: '.$result->resolverCandidateRows);
        $this->line('Blocked rows: '.$result->blockedRows);

        if ($result->triageCounts !== []) {
            $this->table(['Triage', 'Rows'], collect($result->triageCounts)->map(
                fn (int $count, string $triage): array => [$triage, (string) $count]
            )->values()->all());
        }

        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyUrlContinuityTriageResultDTO $result): array
    {
        return [
            'source_path' => $result->sourcePath,
            'disk' => $result->disk,
            'scanned_rows' => $result->scannedRows,
            'unresolved_rows' => $result->unresolvedRows,
            'resolver_candidate_rows' => $result->resolverCandidateRows,
            'blocked_rows' => $result->blockedRows,
            'triage_counts' => $result->triageCounts,
            'handler_counts' => $result->handlerCounts,
            'warnings' => $result->warnings,
            'paths' => $result->paths,
        ];
    }
}
