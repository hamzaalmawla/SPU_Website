<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

/**
 * Localized emergency notice payload for the public shell.
 */
final readonly class EmergencyNoticeDTO
{
    public function __construct(
        public string $locale,
        public bool $isEnabled,
        public ?string $title = null,
        public ?string $message = null,
        public ?string $url = null,
    ) {}
}
