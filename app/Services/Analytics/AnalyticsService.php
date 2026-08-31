<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Contracts\Analytics\AnalyticsServiceInterface;
use App\DTOs\Analytics\AnalyticsSnippetDTO;

/**
 * Config-driven analytics resolution.
 *
 * Reads config/analytics.php and nothing else: no database, no cache, no HTTP.
 * With `config:cache` in production this is a plain array lookup, which keeps
 * per-request cost at effectively zero on the 5-worker, no-OPcache host.
 */
final class AnalyticsService implements AnalyticsServiceInterface
{
    public function isEnabled(): bool
    {
        return config('analytics.enabled') === true;
    }

    public function snippet(bool $isPreview = false): ?AnalyticsSnippetDTO
    {
        // Editors previewing unpublished content must never be counted, and a
        // preview page is noindex anyway.
        if ($isPreview || ! $this->isEnabled()) {
            return null;
        }

        $provider = config('analytics.provider');
        $measurementId = config('analytics.measurement_id');
        $scriptUrl = config('analytics.script_url');

        if (! is_string($provider) || ! is_string($measurementId) || ! is_string($scriptUrl)) {
            return null;
        }

        if ($provider === '' || $measurementId === '' || $scriptUrl === '') {
            return null;
        }

        return new AnalyticsSnippetDTO(
            provider: $provider,
            measurementId: $measurementId,
            scriptUrl: $scriptUrl,
            options: $this->options(),
        );
    }

    public function contentSecurityPolicySources(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $configured = config('analytics.csp');

        if (! is_array($configured)) {
            return [];
        }

        $sources = [];

        foreach ($configured as $directive => $origins) {
            if (! is_string($directive) || ! is_array($origins)) {
                continue;
            }

            $clean = array_values(array_filter(
                $origins,
                static fn (mixed $origin): bool => is_string($origin) && $origin !== '',
            ));

            if ($clean !== []) {
                $sources[$directive] = $clean;
            }
        }

        return $sources;
    }

    /**
     * @return array<string, scalar>
     */
    private function options(): array
    {
        $configured = config('analytics.options');

        if (! is_array($configured)) {
            return [];
        }

        $options = [];

        foreach ($configured as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $options[$key] = $value;
            }
        }

        return $options;
    }
}
