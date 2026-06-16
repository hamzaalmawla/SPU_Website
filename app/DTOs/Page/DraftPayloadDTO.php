<?php

declare(strict_types=1);

namespace App\DTOs\Page;

/**
 * Structured persisted draft content separated from rendered preview payloads.
 */
final readonly class DraftPayloadDTO
{
    public function __construct(
        public ?PageDraftDataDTO $page = null,
        public ?HomepageDraftDataDTO $homepage = null,
    ) {}
}
