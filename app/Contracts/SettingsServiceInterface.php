<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\ApplyCtaSettingsDTO;
use App\DTOs\EmergencyNoticeDTO;
use App\DTOs\FooterSettingsDTO;
use App\DTOs\PageSeoDTO;
use App\DTOs\PublicSettingsDTO;
use App\DTOs\SettingsDTO;
use App\DTOs\SocialContactSettingsDTO;

/**
 * Defines grouped settings access for navigation, footer, notices, and SEO.
 */
interface SettingsServiceInterface
{
    /**
     * Retrieve a settings group, localized when applicable.
     */
    public function getGroup(string $group, ?string $locale = null): SettingsDTO;

    /**
     * Retrieve the public settings payload used by the public shell.
     */
    public function getPublicSettings(string $locale): PublicSettingsDTO;

    /**
     * Update a settings group.
     */
    public function updateGroup(SettingsDTO $values, int $userId): bool;

    /**
     * Retrieve the localized apply CTA target payload.
     */
    public function getApplyCtaTarget(string $locale): ApplyCtaSettingsDTO;

    /**
     * Retrieve the student portal URL, or null when not configured.
     */
    public function getStudentPortalUrl(): ?string;

    /**
     * Retrieve the staff access URL, or null when not configured.
     */
    public function getStaffAccessUrl(): ?string;

    /**
     * Retrieve the localized emergency notice payload.
     */
    public function getEmergencyNotice(string $locale): EmergencyNoticeDTO;

    /**
     * Retrieve localized footer settings.
     */
    public function getFooterSettings(string $locale): FooterSettingsDTO;

    /**
     * Retrieve localized social and contact settings.
     */
    public function getSocialContactSettings(string $locale): SocialContactSettingsDTO;

    /**
     * Retrieve default localized SEO settings.
     */
    public function getDefaultSeoSettings(string $locale): PageSeoDTO;
}
