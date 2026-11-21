<?php

declare(strict_types=1);

namespace App\Contracts\Analytics;

use App\DTOs\Analytics\AnalyticsSnippetDTO;

/**
 * Resolves the configured analytics provider.
 *
 * Both the injected <script> tag and the Content-Security-Policy are derived
 * from this one contract, so the policy and the script can never disagree:
 * if the snippet is null, no analytics origin is added to the CSP.
 *
 * Implementations must read configuration only — never the database — because
 * this runs on every public request.
 */
interface AnalyticsServiceInterface
{
    /**
     * Whether analytics is configured and permitted in this environment.
     */
    public function isEnabled(): bool;

    /**
     * The tag to inject, or null when analytics must not run.
     *
     * @param  bool  $isPreview  True inside the tokenized preview shell, where
     *                           editor traffic must never reach analytics.
     */
    public function snippet(bool $isPreview = false): ?AnalyticsSnippetDTO;

    /**
     * CSP origins the active provider needs, keyed by directive.
     *
     * Empty when analytics is off, which leaves the strict default policy
     * untouched.
     *
     * @return array<string, list<string>>
     */
    public function contentSecurityPolicySources(): array;
}
