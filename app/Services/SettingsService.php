<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\SettingsServiceInterface;
use App\DTOs\ApplyCtaSettingsDTO;
use App\DTOs\ContactLinkDTO;
use App\DTOs\EmergencyNoticeDTO;
use App\DTOs\FooterSettingsDTO;
use App\DTOs\NavigationActionDTO;
use App\DTOs\PageSeoDTO;
use App\DTOs\PublicSettingsDTO;
use App\DTOs\SettingsDTO;
use App\DTOs\SettingValueDTO;
use App\DTOs\SocialContactSettingsDTO;
use App\DTOs\SocialLinkDTO;
use App\Models\Setting;
use App\Support\UrlSanitizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Index coverage for hot-path queries (verified 2026-04-30):
 * ──────────────────────────────────────────────────────────
 * getGroup():
 *   → forGroup($group) + orderBy('key') + orderBy('locale') + optional whereIn('locale', [...])
 *   → settings has UNIQUE(group_key, key, locale) — fully covered as a composite index
 *   → group_key also has a standalone index for group-only lookups
 *
 * findSetting() (used by jsonSetting/textSetting):
 *   → where('group_key', $group) + where('key', $key) + whereIn('locale', [$locale, ''])
 *   → settings has UNIQUE(group_key, key, locale) — fully covered
 *
 * updateGroup():
 *   → updateOrCreate(['group_key' => ..., 'key' => ..., 'locale' => ...], ...)
 *   → settings has UNIQUE(group_key, key, locale) — fully covered for upsert
 */
final class SettingsService implements SettingsServiceInterface
{
    public function __construct(
        private readonly CacheServiceInterface $cacheService,
        private readonly AuditServiceInterface $auditService,
    ) {}

    public function getGroup(string $group, ?string $locale = null): SettingsDTO
    {
        $this->assertGroupKey($group);

        $cacheKey = $this->groupCacheKey($group, $locale);

        $settings = $this->cacheService->remember(
            $cacheKey,
            function () use ($group, $locale): SettingsDTO {
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
            },
            (int) config('cache.settings_ttl', 21600),
        );

        if (! $settings instanceof SettingsDTO) {
            throw new InvalidArgumentException('Settings cache returned an unexpected payload.');
        }

        return $settings;
    }

    public function getPublicSettings(string $locale): PublicSettingsDTO
    {
        $locale = $this->normalizeLocale($locale);

        $settings = $this->cacheService->remember(
            $this->publicSettingsCacheKey($locale),
            fn (): PublicSettingsDTO => new PublicSettingsDTO(
                locale: $locale,
                direction: $this->directionForLocale($locale),
                applyCta: $this->getApplyCtaTarget($locale),
                emergencyNotice: $this->getEmergencyNotice($locale),
                footer: $this->getFooterSettings($locale),
                socialContact: $this->getSocialContactSettings($locale),
                defaultSeo: $this->getDefaultSeoSettings($locale),
                studentPortalUrl: $this->getStudentPortalUrl(),
                staffAccessUrl: $this->getStaffAccessUrl(),
            ),
            (int) config('cache.settings_ttl', 21600),
        );

        if (! $settings instanceof PublicSettingsDTO) {
            throw new InvalidArgumentException('Public settings cache returned an unexpected payload.');
        }

        return $settings;
    }

    public function updateGroup(SettingsDTO $values, ?int $userId = null): bool
    {
        $this->assertGroupKey($values->group);
        $locale = $this->normalizeLocaleOrBlank($values->locale);

        DB::transaction(function () use ($values, $locale): void {
            foreach ($values->values as $value) {
                if (! $value instanceof SettingValueDTO) {
                    throw new InvalidArgumentException('Settings group updates require SettingValueDTO values.');
                }

                $value = $this->sanitizeSettingValue($value);

                if ($value->jsonValue === null && $value->textValue === null) {
                    throw new InvalidArgumentException('Each setting update requires either jsonValue or textValue.');
                }

                Setting::query()->updateOrCreate(
                    [
                        'group_key' => $values->group,
                        'key' => $value->key,
                        'locale' => $locale,
                    ],
                    [
                        'type' => $value->type,
                        'value_json' => $value->jsonValue,
                        'value_text' => $value->textValue,
                        'is_public' => $value->isPublic,
                    ],
                );
            }
        });

        $this->invalidateSettingsCaches($values->group, $locale !== '' ? [$locale] : ['ar', 'en']);

        $metadata = [
            'group_key' => $values->group,
            'locale' => $locale,
            'keys' => array_values(array_map(
                static fn (SettingValueDTO $value): string => $value->key,
                array_filter($values->values, static fn (mixed $value): bool => $value instanceof SettingValueDTO),
            )),
            'affects_footer' => $values->group === 'footer',
            'affects_utility_shell' => in_array($values->group, ['navigation', 'public_shell'], true),
        ];

        $this->auditService->log(
            action: 'settings.update',
            userId: $userId,
            entityType: Setting::class,
            metadata: $metadata,
        );

        if ($values->group === 'footer') {
            $this->auditService->log(
                action: 'settings.footer_updated',
                userId: $userId,
                entityType: Setting::class,
                metadata: $metadata,
            );
        }

        if (in_array($values->group, ['navigation', 'public_shell'], true)) {
            $this->auditService->log(
                action: 'settings.utility_shell_updated',
                userId: $userId,
                entityType: Setting::class,
                metadata: $metadata,
            );
        }

        return true;
    }

    public function getApplyCtaTarget(string $locale): ApplyCtaSettingsDTO
    {
        $locale = $this->normalizeLocale($locale);

        $payload = $this->cacheService->remember(
            $this->applyCtaCacheKey($locale),
            fn (): ApplyCtaSettingsDTO => $this->buildApplyCtaTarget($locale),
            (int) config('cache.settings_ttl', 21600),
        );

        if (! $payload instanceof ApplyCtaSettingsDTO) {
            throw new InvalidArgumentException('Apply CTA cache returned an unexpected payload.');
        }

        return $payload;
    }

    public function getStudentPortalUrl(): ?string
    {
        $value = $this->cacheService->remember(
            $this->studentPortalCacheKey(),
            fn (): ?string => $this->textSetting('navigation', 'student_portal_url'),
            (int) config('cache.settings_ttl', 21600),
        );

        return is_string($value) && $value !== '' ? UrlSanitizer::sanitize($value) : null;
    }

    public function getStaffAccessUrl(): ?string
    {
        $value = $this->cacheService->remember(
            $this->staffAccessCacheKey(),
            fn (): ?string => $this->textSetting('navigation', 'staff_access_url'),
            (int) config('cache.settings_ttl', 21600),
        );

        return is_string($value) && $value !== '' ? UrlSanitizer::sanitize($value) : null;
    }

    public function getEmergencyNotice(string $locale): EmergencyNoticeDTO
    {
        $locale = $this->normalizeLocale($locale);

        $payload = $this->cacheService->remember(
            $this->emergencyNoticeCacheKey($locale),
            fn (): EmergencyNoticeDTO => $this->buildEmergencyNotice($locale),
            (int) config('cache.settings_ttl', 21600),
        );

        if (! $payload instanceof EmergencyNoticeDTO) {
            throw new InvalidArgumentException('Emergency notice cache returned an unexpected payload.');
        }

        return $payload;
    }

    public function getFooterSettings(string $locale): FooterSettingsDTO
    {
        $locale = $this->normalizeLocale($locale);

        $payload = $this->cacheService->remember(
            $this->footerCacheKey($locale),
            fn (): FooterSettingsDTO => $this->buildFooterSettings($locale),
            (int) config('cache.settings_ttl', 21600),
        );

        if (! $payload instanceof FooterSettingsDTO) {
            throw new InvalidArgumentException('Footer settings cache returned an unexpected payload.');
        }

        return $payload;
    }

    public function getSocialContactSettings(string $locale): SocialContactSettingsDTO
    {
        $locale = $this->normalizeLocale($locale);

        $payload = $this->cacheService->remember(
            $this->socialContactCacheKey($locale),
            fn (): SocialContactSettingsDTO => $this->buildSocialContactSettings($locale),
            (int) config('cache.settings_ttl', 21600),
        );

        if (! $payload instanceof SocialContactSettingsDTO) {
            throw new InvalidArgumentException('Social contact cache returned an unexpected payload.');
        }

        return $payload;
    }

    public function getDefaultSeoSettings(string $locale): PageSeoDTO
    {
        $locale = $this->normalizeLocale($locale);

        $payload = $this->cacheService->remember(
            $this->defaultSeoCacheKey($locale),
            fn (): PageSeoDTO => $this->buildDefaultSeoSettings($locale),
            (int) config('cache.settings_ttl', 21600),
        );

        if (! $payload instanceof PageSeoDTO) {
            throw new InvalidArgumentException('Default SEO cache returned an unexpected payload.');
        }

        return $payload;
    }

    private function buildApplyCtaTarget(string $locale): ApplyCtaSettingsDTO
    {
        $payload = $this->jsonSetting('navigation', 'apply_cta', $locale);

        return new ApplyCtaSettingsDTO(
            locale: $locale,
            label: $this->stringValue($payload, 'label') ?? $this->defaultApplyLabel($locale),
            url: UrlSanitizer::sanitize($this->stringValue($payload, 'url')) ?? '/'.$locale,
            target: $this->stringValue($payload, 'target'),
            isEnabled: $this->boolValue($payload, 'is_enabled', true),
        );
    }

    private function buildEmergencyNotice(string $locale): EmergencyNoticeDTO
    {
        $payload = $this->jsonSetting('public_shell', 'emergency_notice', $locale);

        return new EmergencyNoticeDTO(
            locale: $locale,
            isEnabled: $this->boolValue($payload, 'is_enabled', false),
            title: $this->stringValue($payload, 'title'),
            message: $this->stringValue($payload, 'message'),
            url: UrlSanitizer::sanitize($this->stringValue($payload, 'url')),
        );
    }

    private function buildFooterSettings(string $locale): FooterSettingsDTO
    {
        $payload = $this->jsonSetting('footer', 'footer', $locale);
        $brandBlock = is_array($payload['brandBlock'] ?? ($payload['brand_block'] ?? null))
            ? ($payload['brandBlock'] ?? $payload['brand_block'])
            : [];
        $mapEmbed = is_array($payload['mapEmbed'] ?? ($payload['map_embed'] ?? null))
            ? ($payload['mapEmbed'] ?? $payload['map_embed'])
            : [];

        return new FooterSettingsDTO(
            locale: $locale,
            copyrightText: $this->stringValue($payload, 'copyrightText')
                ?? $this->stringValue($payload, 'copyright_text')
                ?? config('app.name', 'SPU'),
            address: $this->stringValue($payload, 'address'),
            phone: $this->stringValue($payload, 'phone'),
            email: $this->stringValue($payload, 'email'),
            brandTitle: $this->stringValue($brandBlock, 'title') ?? $this->stringValue($payload, 'brandTitle') ?? config('app.name', 'SPU'),
            brandSummary: $this->stringValue($brandBlock, 'body') ?? $this->stringValue($brandBlock, 'summary') ?? $this->stringValue($payload, 'brandSummary'),
            logoUrl: UrlSanitizer::sanitize($this->stringValue($brandBlock, 'logoUrl') ?? $this->stringValue($brandBlock, 'logo_url') ?? $this->stringValue($payload, 'logoUrl'), ['http', 'https'], true),
            mapEmbedUrl: UrlSanitizer::sanitize($this->stringValue($mapEmbed, 'url') ?? $this->stringValue($mapEmbed, 'embedUrl') ?? $this->stringValue($payload, 'mapEmbedUrl'), ['https'], false),
            legalLinks: $this->actionList($payload['legalLinks'] ?? ($payload['legal_links'] ?? [])),
        );
    }

    private function buildSocialContactSettings(string $locale): SocialContactSettingsDTO
    {
        $socialPayload = $this->jsonSetting('footer', 'social_contact', $locale);
        $contactPayload = $this->jsonSetting('footer', 'contact_links', $locale);

        $socialLinks = array_map(
            static fn (array $item): SocialLinkDTO => new SocialLinkDTO(
                platform: (string) ($item['platform'] ?? $item['label'] ?? 'Social'),
                url: UrlSanitizer::sanitize(is_string($item['url'] ?? null) ? $item['url'] : null) ?? '#',
                isEnabled: (bool) ($item['is_enabled'] ?? ($item['isEnabled'] ?? true)),
            ),
            $this->listValue($socialPayload, 'socialLinks', 'social_links'),
        );

        $contactLinks = array_map(
            static fn (array $item): ContactLinkDTO => new ContactLinkDTO(
                type: (string) ($item['type'] ?? 'text'),
                label: (string) ($item['label'] ?? $item['value'] ?? ''),
                value: (string) ($item['value'] ?? ''),
            ),
            $this->listValue($contactPayload, 'contactLinks', 'contact_links'),
        );

        return new SocialContactSettingsDTO($locale, $socialLinks, $contactLinks);
    }

    private function buildDefaultSeoSettings(string $locale): PageSeoDTO
    {
        $payload = $this->jsonSetting('seo', 'default_seo', $locale);
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

        return new PageSeoDTO(
            locale: $locale,
            title: $this->stringValue($payload, 'title') ?? config('app.name', 'SPU'),
            metaDescription: $this->stringValue($payload, 'meta_description') ?? $this->stringValue($payload, 'metaDescription'),
            ogTitle: $this->stringValue($payload, 'og_title') ?? $this->stringValue($payload, 'ogTitle') ?? $this->stringValue($payload, 'title') ?? config('app.name', 'SPU'),
            ogDescription: $this->stringValue($payload, 'og_description') ?? $this->stringValue($payload, 'ogDescription') ?? $this->stringValue($payload, 'meta_description') ?? $this->stringValue($payload, 'metaDescription'),
            ogImage: UrlSanitizer::sanitize($this->stringValue($payload, 'og_image') ?? $this->stringValue($payload, 'ogImage'), ['http', 'https'], true),
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

    private function assertGroupKey(string $group): void
    {
        if (! in_array($group, Setting::GROUP_KEYS, true)) {
            throw new InvalidArgumentException('Unsupported settings group: '.$group);
        }
    }

    private function normalizeLocale(string $locale): string
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            throw new InvalidArgumentException('Settings locale must be ar or en.');
        }

        return $locale;
    }

    private function normalizeLocaleOrBlank(?string $locale): string
    {
        if ($locale === null || $locale === '') {
            return '';
        }

        return $this->normalizeLocale($locale);
    }

    private function directionForLocale(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }

    private function defaultApplyLabel(string $locale): string
    {
        return $locale === 'ar' ? 'قدّم الآن' : 'Apply now';
    }

    private function groupCacheKey(string $group, ?string $locale): string
    {
        return 'settings.group.'.$group.'.'.($locale === null || $locale === '' ? 'all' : $locale);
    }

    private function publicSettingsCacheKey(string $locale): string
    {
        return 'settings.public.'.$locale;
    }

    private function applyCtaCacheKey(string $locale): string
    {
        return 'settings.apply_cta.'.$locale;
    }

    private function studentPortalCacheKey(): string
    {
        return 'settings.student_portal_url';
    }

    private function staffAccessCacheKey(): string
    {
        return 'settings.staff_access_url';
    }

    private function emergencyNoticeCacheKey(string $locale): string
    {
        return 'settings.emergency_notice.'.$locale;
    }

    private function footerCacheKey(string $locale): string
    {
        return 'settings.footer.'.$locale;
    }

    private function socialContactCacheKey(string $locale): string
    {
        return 'settings.social_contact.'.$locale;
    }

    private function defaultSeoCacheKey(string $locale): string
    {
        return 'settings.default_seo.'.$locale;
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function invalidateSettingsCaches(string $group, array $locales): void
    {
        $normalizedLocales = array_values(array_unique(array_filter($locales, static fn (mixed $locale): bool => is_string($locale) && in_array($locale, ['ar', 'en'], true))));

        $this->cacheService->forget($this->groupCacheKey($group, null));

        foreach ($normalizedLocales as $locale) {
            $this->cacheService->forget($this->groupCacheKey($group, $locale));
            $this->cacheService->forget($this->publicSettingsCacheKey($locale));
            $this->cacheService->forget('navigation.payload.'.$locale);

            if ($group === 'navigation') {
                $this->cacheService->forget($this->applyCtaCacheKey($locale));
            }

            if ($group === 'public_shell') {
                $this->cacheService->forget($this->emergencyNoticeCacheKey($locale));
            }

            if ($group === 'footer') {
                $this->cacheService->forget($this->footerCacheKey($locale));
                $this->cacheService->forget($this->socialContactCacheKey($locale));
            }

            if ($group === 'seo') {
                $this->cacheService->forget($this->defaultSeoCacheKey($locale));
            }
        }

        if ($group === 'navigation') {
            $this->cacheService->forget($this->studentPortalCacheKey());
            $this->cacheService->forget($this->staffAccessCacheKey());
        }

        $this->cacheService->flushTags(
            in_array($group, ['navigation', 'footer', 'public_shell'], true)
                ? ['public-pages', 'public-shell', 'settings', 'navigation']
                : ['settings'],
        );
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

    /**
     * @return array<int, NavigationActionDTO>
     */
    private function actionList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?NavigationActionDTO {
            if (! is_array($item)) {
                return null;
            }

            $label = $this->stringValue($item, 'label');
            $url = $this->stringValue($item, 'url');

            if ($label === null || $url === null) {
                return null;
            }

            $url = UrlSanitizer::sanitize($url);

            if ($url === null) {
                return null;
            }

            return new NavigationActionDTO(
                label: $label,
                url: $url,
                target: $this->stringValue($item, 'target'),
            );
        }, $value)));
    }

    private function sanitizeSettingValue(SettingValueDTO $value): SettingValueDTO
    {
        return new SettingValueDTO(
            key: $value->key,
            type: $value->type,
            jsonValue: is_array($value->jsonValue) ? $this->sanitizeUrlsRecursively($value->jsonValue) : null,
            textValue: $this->keyLooksLikeUrl($value->key) ? UrlSanitizer::sanitize($value->textValue) : $value->textValue,
            isPublic: $value->isPublic,
        );
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $payload
     * @return array<string, mixed>|array<int, mixed>
     */
    private function sanitizeUrlsRecursively(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sanitizeUrlsRecursively($value);

                continue;
            }

            if (is_string($value) && $this->keyLooksLikeUrl((string) $key)) {
                $payload[$key] = UrlSanitizer::sanitize($value) ?? '';
            }
        }

        return $payload;
    }

    private function keyLooksLikeUrl(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'url') || in_array($key, ['href', 'src'], true);
    }
}
