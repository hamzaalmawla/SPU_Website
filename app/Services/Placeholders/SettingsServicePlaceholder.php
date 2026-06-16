<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Settings\ApplyCtaSettingsDTO;
use App\DTOs\Settings\EmergencyNoticeDTO;
use App\DTOs\Settings\FooterSettingsDTO;
use App\DTOs\Seo\PageSeoDTO;
use App\DTOs\Settings\PublicSettingsDTO;
use App\DTOs\Settings\SettingsDTO;
use App\DTOs\Settings\SocialContactSettingsDTO;
use BadMethodCallException;

/**
 * Placeholder implementation for the grouped settings service contract.
 */
final class SettingsServicePlaceholder implements SettingsServiceInterface
{
    public function getGroup(string $group, ?string $locale = null): SettingsDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getPublicSettings(string $locale): PublicSettingsDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateGroup(SettingsDTO $values, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getApplyCtaTarget(string $locale): ApplyCtaSettingsDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getStudentPortalUrl(): ?string
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getStaffAccessUrl(): ?string
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getEmergencyNotice(string $locale): EmergencyNoticeDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getFooterSettings(string $locale): FooterSettingsDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getSocialContactSettings(string $locale): SocialContactSettingsDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getDefaultSeoSettings(string $locale): PageSeoDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
