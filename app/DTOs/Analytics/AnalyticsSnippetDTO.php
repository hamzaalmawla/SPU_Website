<?php

declare(strict_types=1);

namespace App\DTOs\Analytics;

/**
 * Everything the layout needs to emit an analytics tag.
 *
 * Built from configuration only; never from the database.
 */
final readonly class AnalyticsSnippetDTO
{
    /**
     * @param  array<string, scalar>  $options  gtag config options (privacy flags).
     */
    public function __construct(
        public string $provider,
        public string $measurementId,
        public string $scriptUrl,
        public array $options = [],
    ) {}
}
