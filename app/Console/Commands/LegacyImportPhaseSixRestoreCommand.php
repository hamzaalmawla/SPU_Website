<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPhaseSixRestoreServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportPhaseSixRestoreCommand extends Command
{
    protected $signature = 'legacy-import:phase6-restore
        {--write : Persist every approved Phase 6 lane}
        {--approve= : Required umbrella approval token for write mode}
        {--batch= : Optional common migration batch prefix}
        {--json : Output machine-readable JSON}';

    protected $description = 'Rebuild Phase 6 review prerequisites and restore every approved legacy import lane.';

    public function __construct(
        private readonly LegacyPhaseSixRestoreServiceInterface $restoreService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->restoreService->restore(
                write: (bool) $this->option('write'),
                approval: is_string($this->option('approve')) ? (string) $this->option('approve') : null,
                batch: is_string($this->option('batch')) ? (string) $this->option('batch') : null,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $payload = [
            'written' => $result->written,
            'batch' => $result->batch,
            'lanes' => $result->lanes,
            'warnings' => $result->warnings,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Phase 6 Legacy Restore');
        $this->line('Mode: '.($result->written ? 'write' : 'dry-run'));
        $this->line('Batch: '.$result->batch);

        foreach ($result->lanes as $lane => $summary) {
            $this->line($lane.': '.(string) json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
