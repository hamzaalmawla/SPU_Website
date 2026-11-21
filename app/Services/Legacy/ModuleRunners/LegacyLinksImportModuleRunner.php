<?php

declare(strict_types=1);

namespace App\Services\Legacy\ModuleRunners;

use App\Contracts\Legacy\LegacyImportModuleRunnerInterface;
use App\DTOs\Legacy\LegacyImportDryRunDTO;
use App\DTOs\Legacy\LegacyImportModuleRunnerDTO;

final class LegacyLinksImportModuleRunner implements LegacyImportModuleRunnerInterface
{
    public function definition(): LegacyImportModuleRunnerDTO
    {
        return new LegacyImportModuleRunnerDTO(
            module: 'links',
            label: 'Legacy links and document metadata',
            approvedForRealRun: false,
            approvalStatus: 'candidate_not_approved',
            description: 'First low-risk candidate for controlled import runner implementation. Real execution remains blocked.',
        );
    }

    public function canExecute(LegacyImportDryRunDTO $dryRun): bool
    {
        return $dryRun->canRun && $this->definition()->approvedForRealRun;
    }

    public function blockedReason(LegacyImportDryRunDTO $dryRun): string
    {
        if (! $dryRun->canRun) {
            return 'Legacy links runner cannot execute because dry-run validation is not clean.';
        }

        return 'Controlled legacy links runner is registered, but real execution is not approved yet.';
    }
}
