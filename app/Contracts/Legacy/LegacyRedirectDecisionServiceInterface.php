<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyRedirectDecisionResultDTO;

interface LegacyRedirectDecisionServiceInterface
{
    public function decide(
        string $input,
        string $disk = 'local',
        bool $write = false,
        ?string $approval = null,
        ?string $batch = null,
    ): LegacyRedirectDecisionResultDTO;

    public function rollback(
        string $batch,
        bool $write = false,
        ?string $approval = null,
    ): LegacyRedirectDecisionResultDTO;
}
