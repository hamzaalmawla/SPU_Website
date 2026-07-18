<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use App\DTOs\Legacy\PatternRuleDTO;
use App\DTOs\Legacy\RedirectResultDTO;
use App\DTOs\Legacy\RedirectRuleDTO;
use App\DTOs\Legacy\UnresolvedRequestDTO;
use App\DTOs\Media\FileInventoryItemDTO;
use App\DTOs\Shared\ValidationResultDTO;
use Illuminate\Support\Collection;

/**
 * Defines redirect and file continuity resolution for legacy URL support.
 */
interface ContinuityServiceInterface
{
    /**
     * Resolve a redirect for the given request path. Returns null if no match.
     */
    public function resolveRedirect(
        string $path,
        ?string $queryString = null,
        ?string $preferredLocale = null,
    ): ?RedirectResultDTO;

    /**
     * Resolve a legacy file path to a current delivery URL. Returns null if no match.
     */
    public function resolveFileContinuity(string $path): ?string;

    /**
     * Log an unresolved legacy request.
     */
    public function logUnresolved(UnresolvedRequestDTO $request): bool;

    /**
     * Get all exact redirect rules.
     *
     * @return Collection<int, RedirectRuleDTO>
     */
    public function getExactRedirects(): Collection;

    /**
     * Get all pattern redirect rules ordered by priority.
     *
     * @return Collection<int, PatternRuleDTO>
     */
    public function getPatternRules(): Collection;

    /**
     * Validate redirect rules for conflicts, duplicates, loops.
     */
    public function validateRedirectRules(): ValidationResultDTO;

    /**
     * Get unresolved requests with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, UnresolvedRequestDTO>
     */
    public function getUnresolvedRequests(array $filters = []): Collection;

    /**
     * Get file continuity inventory.
     *
     * @return Collection<int, FileInventoryItemDTO>
     */
    public function getFileInventory(): Collection;
}
