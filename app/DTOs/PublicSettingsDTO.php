<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Public settings payload used by the public shell.
 */
final readonly class PublicSettingsDTO
{
    public function __construct(
        public string $locale,
        public string $direction,
        public ApplyCtaSettingsDTO $applyCta,
        public EmergencyNoticeDTO $emergencyNotice,
        public FooterSettingsDTO $footer,
        public SocialContactSettingsDTO $socialContact,
        public PageSeoDTO $defaultSeo,
        public ?string $studentPortalUrl,
        public ?string $staffAccessUrl,
    ) {}
}
