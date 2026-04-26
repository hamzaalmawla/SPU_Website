<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Full public navigation payload combining trees, links, and notices.
 */
final readonly class NavigationPayloadDTO
{
    /**
     * @param  array<int, LanguageSwitchLinkDTO>  $languageSwitchLinks
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public NavigationTreeDTO $header,
        public NavigationTreeDTO $footer,
        public NavigationTreeDTO $utility,
            public array $languageSwitchLinks,
            public ?NavigationActionDTO $applyCta,
            public ?string $studentPortalUrl,
            public ?string $staffAccessUrl,
            public EmergencyNoticeDTO $emergencyNotice,
            public FooterSettingsDTO $footerSettings,
            public SocialContactSettingsDTO $socialContact,
    ) {}
}
