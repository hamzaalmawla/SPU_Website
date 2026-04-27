<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\RedirectResultDTO;
use App\DTOs\UnresolvedRequestDTO;
use App\DTOs\ValidationResultDTO;
use Illuminate\Support\Collection;

/**
 * Defines redirect and file continuity resolution for legacy URL support.
 */
interface ContinuityServiceInterface
{
    /**
     * Resolve a redirect for the given request path. Returns null if no match.
     */
    public function resolveRedirect(string $path, ?string $queryString = null): ?RedirectResultDTO;

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
     * @return Collection<int, \App\DTOs\RedirectRuleDTO>
     */
    public function getExactRedirects(): Collection;

    /**
     * Get all pattern redirect rules ordered by priority.
     *
     * @return Collection<int, \App\DTOs\PatternRuleDTO>
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
     * @return Collection<int, \App\DTOs\UnresolvedRequestDTO>
     */
    public function getUnresolvedRequests(array $filters = []): Collection;

    /**
     * Get file continuity inventory.
     *
     * @return Collection<int, \App\DTOs\FileInventoryItemDTO>
     */
    public function getFileInventory(): Collection;
}
