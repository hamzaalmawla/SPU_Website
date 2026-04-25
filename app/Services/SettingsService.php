<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SettingsServiceInterface;
use App\DTOs\ApplyCtaSettingsDTO;
use App\DTOs\ContactLinkDTO;
use App\DTOs\EmergencyNoticeDTO;
use App\DTOs\FooterSettingsDTO;
use App\DTOs\PageSeoDTO;
use App\DTOs\PublicSettingsDTO;
use App\DTOs\SettingsDTO;
use App\DTOs\SettingValueDTO;
use App\DTOs\SocialContactSettingsDTO;
use App\DTOs\SocialLinkDTO;
use App\Models\Setting;
use BadMethodCallException;

final class SettingsService implements SettingsServiceInterface
{
    public function getGroup(string $group, ?string $locale = null): SettingsDTO
    {
        $query = Setting::query()
            ->forGroup($group)
            ->orderBy('key')
            ->orderBy('locale');

        if ($locale !== null) {
            $query->whereIn('locale', [$locale, '']);
        }

        $values = $query
            ->get()
            ->map(static fn (Setting $setting): SettingValueDTO => new SettingValueDTO(
                key: (string) $setting->key,
                type: (string) $setting->type,
                jsonValue: is_array($setting->value_json) ? $setting->value_json : null,
                textValue: $setting->value_text,
                isPublic: (bool) $setting->is_public,
            ))
            ->values()
            ->all();

        return new SettingsDTO($group, $locale, $values);
    }

    public function getPublicSettings(string $locale): PublicSettingsDTO
    {
        return new PublicSettingsDTO(
            locale: $locale,
            direction: $this->directionForLocale($locale),
            applyCta: $this->getApplyCtaTarget($locale),
            emergencyNotice: $this->getEmergencyNotice($locale),
            footer: $this->getFooterSettings($locale),
            socialContact: $this->getSocialContactSettings($locale),
            defaultSeo: $this->getDefaultSeoSettings($locale),
            studentPortalUrl: $this->getStudentPortalUrl(),
            staffAccessUrl: $this->getStaffAccessUrl(),
        );
    }

    public function updateGroup(SettingsDTO $values, ?int $userId = null): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function getApplyCtaTarget(string $locale): ApplyCtaSettingsDTO
    {
        $payload = $this->jsonSetting('navigation', 'apply_cta', $locale);

        return new ApplyCtaSettingsDTO(
            locale: $locale,
            label: $this->stringValue($payload, 'label') ?? $this->defaultApplyLabel($locale),
            url: $this->stringValue($payload, 'url') ?? '/'.$locale,
            target: $this->stringValue($payload, 'target'),
            isEnabled: $this->boolValue($payload, 'is_enabled', true),
        );
    }

    public function getStudentPortalUrl(): ?string
    {
        return $this->textSetting('navigation', 'student_portal_url');
    }

    public function getStaffAccessUrl(): ?string
    {
        return $this->textSetting('navigation', 'staff_access_url');
    }

    public function getEmergencyNotice(string $locale): EmergencyNoticeDTO
    {
        $payload = $this->jsonSetting('public_shell', 'emergency_notice', $locale);

        return new EmergencyNoticeDTO(
            locale: $locale,
            isEnabled: $this->boolValue($payload, 'is_enabled', false),
            title: $this->stringValue($payload, 'title'),
            message: $this->stringValue($payload, 'message'),
            url: $this->stringValue($payload, 'url'),
        );
    }

    public function getFooterSettings(string $locale): FooterSettingsDTO
    {
        $payload = $this->jsonSetting('footer', 'footer', $locale);

        return new FooterSettingsDTO(
            locale: $locale,
            copyrightText: $this->stringValue($payload, 'copyrightText')
                ?? $this->stringValue($payload, 'copyright_text')
                ?? config('app.name', 'SPU'),
            address: $this->stringValue($payload, 'address'),
            phone: $this->stringValue($payload, 'phone'),
            email: $this->stringValue($payload, 'email'),
        );
    }

    public function getSocialContactSettings(string $locale): SocialContactSettingsDTO
    {
        $socialPayload = $this->jsonSetting('footer', 'social_contact', $locale);
        $contactPayload = $this->jsonSetting('footer', 'contact_links', $locale);

        $socialLinks = array_map(
            static fn (array $item): SocialLinkDTO => new SocialLinkDTO(
                platform: (string) ($item['platform'] ?? $item['label'] ?? 'Social'),
                url: (string) ($item['url'] ?? '#'),
                isEnabled: (bool) ($item['is_enabled'] ?? true),
            ),
            $this->listValue($socialPayload, 'socialLinks', 'social_links')
        );

        $contactLinks = array_map(
            static fn (array $item): ContactLinkDTO => new ContactLinkDTO(
                type: (string) ($item['type'] ?? 'text'),
                label: (string) ($item['label'] ?? $item['value'] ?? ''),
                value: (string) ($item['value'] ?? ''),
            ),
            $this->listValue($contactPayload, 'contactLinks', 'contact_links')
        );

        return new SocialContactSettingsDTO($locale, $socialLinks, $contactLinks);
    }

    public function getDefaultSeoSettings(string $locale): PageSeoDTO
    {
        $payload = $this->jsonSetting('seo', 'default_seo', $locale);
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

        return new PageSeoDTO(
            locale: $locale,
            title: $this->stringValue($payload, 'title') ?? config('app.name', 'SPU'),
            metaDescription: $this->stringValue($payload, 'meta_description') ?? $this->stringValue($payload, 'metaDescription'),
            ogTitle: $this->stringValue($payload, 'og_title') ?? $this->stringValue($payload, 'ogTitle') ?? $this->stringValue($payload, 'title') ?? config('app.name', 'SPU'),
            ogDescription: $this->stringValue($payload, 'og_description') ?? $this->stringValue($payload, 'ogDescription') ?? $this->stringValue($payload, 'meta_description') ?? $this->stringValue($payload, 'metaDescription'),
            ogImage: $this->stringValue($payload, 'og_image') ?? $this->stringValue($payload, 'ogImage'),
            canonicalUrl: $baseUrl.'/'.$locale,
            hreflang: [
                ['locale' => 'ar', 'url' => $baseUrl.'/ar'],
                ['locale' => 'en', 'url' => $baseUrl.'/en'],
            ],
            robots: $this->stringValue($payload, 'robots') ?? 'index,follow',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonSetting(string $group, string $key, ?string $locale = null): ?array
    {
        $setting = $this->findSetting($group, $key, $locale);

        return $setting !== null && is_array($setting->value_json) ? $setting->value_json : null;
    }

    private function textSetting(string $group, string $key, ?string $locale = null): ?string
    {
        return $this->findSetting($group, $key, $locale)?->value_text;
    }

    private function findSetting(string $group, string $key, ?string $locale = null): ?Setting
    {
        $query = Setting::query()
            ->where('group_key', $group)
            ->where('key', $key);

        if ($locale === null) {
            return $query->orderByDesc('locale')->first();
        }

        $candidates = $query
            ->whereIn('locale', [$locale, ''])
            ->get();

        return $candidates->firstWhere('locale', $locale) ?? $candidates->firstWhere('locale', '');
    }

    private function directionForLocale(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }

    private function defaultApplyLabel(string $locale): string
    {
        return $locale === 'ar' ? 'قدّم الآن' : 'Apply now';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function stringValue(?array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function boolValue(?array $payload, string $key, bool $default): bool
    {
        $value = $payload[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array<string, mixed>>
     */
    private function listValue(?array $payload, string ...$keys): array
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_array($value)) {
                return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
            }
        }

        return [];
    }
}
