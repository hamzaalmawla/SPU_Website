<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleaningInspectionServiceInterface;
use App\Contracts\Legacy\LegacyImportBatchServiceInterface;
use App\Contracts\Legacy\LegacyImportInspectionServiceInterface;
use App\Contracts\Legacy\LegacyImportModuleRegistryInterface;
use App\Contracts\Legacy\LegacyImportRunnerServiceInterface;
use App\Contracts\Legacy\LegacyIntegrityInspectionServiceInterface;
use App\DTOs\Legacy\LegacyCleaningInspectionResultDTO;
use App\DTOs\Legacy\LegacyImportRunResultDTO;
use App\DTOs\Legacy\LegacyIntegrityInspectionResultDTO;
use Symfony\Component\Console\Command\Command;

final class LegacyImportRunnerService implements LegacyImportRunnerServiceInterface
{
    public function __construct(
        private readonly LegacyImportInspectionServiceInterface $inspectionService,
        private readonly LegacyImportBatchServiceInterface $batchService,
        private readonly LegacyImportModuleRegistryInterface $moduleRegistry,
        private readonly LegacyCleaningInspectionServiceInterface $cleaningInspectionService,
        private readonly LegacyIntegrityInspectionServiceInterface $integrityInspectionService,
    ) {}

    public function run(string $module, ?string $batchName = null, bool $dryRun = false): LegacyImportRunResultDTO
    {
        $inspection = $this->inspectionService->dryRun($module);

        if ($dryRun) {
            $batch = $this->batchService->recordDryRun($inspection, $batchName);

            return new LegacyImportRunResultDTO(
                module: $module,
                mode: 'dry_run',
                status: $batch->status,
                exitCode: $inspection->status === 'unknown_module' ? Command::INVALID : Command::SUCCESS,
                message: 'Dry-run batch recorded:',
                batch: $batch,
                dryRun: $inspection,
            );
        }

        $runnerRegistered = $this->moduleRegistry->find($module) !== null;
        $runnerApproved = $this->moduleRegistry->canExecute($module);
        $cleaningInspection = $inspection->status === 'ready_for_dry_run' && $runnerApproved
            ? $this->cleaningInspectionService->inspect($module)
            : null;
        $integrityInspection = $this->shouldInspectIntegrity($inspection->status, $runnerApproved, $cleaningInspection)
            ? $this->integrityInspectionService->inspect($module)
            : null;
        $reason = $this->blockedRunReason($module, $inspection->status, $runnerApproved, $cleaningInspection, $integrityInspection);
        $batch = $this->batchService->recordBlockedRun($module, $reason, $batchName, [
            'dry_run_status' => $inspection->status,
            'module_enabled' => $inspection->enabled,
            'estimated_source_rows' => $inspection->estimatedSourceRows,
            'controlled_runner_registered' => $runnerRegistered,
            'controlled_runner_approved' => $runnerApproved,
            'cleaning_status' => $cleaningInspection?->status,
            'cleaning_blocked_fields' => $cleaningInspection?->blockedFields,
            'cleaning_issue_counts' => $cleaningInspection?->issueCounts,
            'integrity_status' => $integrityInspection?->status,
            'integrity_blocked_rows' => $integrityInspection?->blockedRows,
            'integrity_issue_counts' => $integrityInspection?->issueCounts,
        ]);

        return new LegacyImportRunResultDTO(
            module: $module,
            mode: 'run',
            status: 'blocked',
            exitCode: $inspection->status === 'unknown_module' ? Command::INVALID : Command::FAILURE,
            message: $reason,
            batch: $batch,
            dryRun: $inspection,
        );
    }

    private function blockedRunReason(string $module, string $dryRunStatus, bool $runnerApproved, ?LegacyCleaningInspectionResultDTO $cleaningInspection, ?LegacyIntegrityInspectionResultDTO $integrityInspection): string
    {
        if ($dryRunStatus === 'unknown_module') {
            return 'Legacy import module is not configured.';
        }

        if ($dryRunStatus === 'disabled') {
            return 'Legacy import module is disabled in config/old_database.php.';
        }

        if ($dryRunStatus === 'connection_unavailable') {
            return 'Legacy import source connection is unavailable.';
        }

        if ($dryRunStatus === 'missing_sources') {
            return 'Legacy import source tables are missing.';
        }

        if (! $runnerApproved) {
            return $this->moduleRegistry->blockedReason($module);
        }

        if ($cleaningInspection instanceof LegacyCleaningInspectionResultDTO && $cleaningInspection->blockedFields > 0) {
            return 'Phase 3 cleaning report has blocked fields. Review or record quarantine before real execution.';
        }

        if ($integrityInspection instanceof LegacyIntegrityInspectionResultDTO && $integrityInspection->blockedRows > 0) {
            return 'Phase 3 integrity report has duplicate or orphan blockers. Review or record quarantine before real execution.';
        }

        return 'Controlled legacy import runner is approved, but real execution is not implemented in this guarded runner yet.';
    }

    private function shouldInspectIntegrity(string $dryRunStatus, bool $runnerApproved, ?LegacyCleaningInspectionResultDTO $cleaningInspection): bool
    {
        if ($dryRunStatus !== 'ready_for_dry_run' || ! $runnerApproved) {
            return false;
        }

        return ! $cleaningInspection instanceof LegacyCleaningInspectionResultDTO || $cleaningInspection->blockedFields === 0;
    }
}
