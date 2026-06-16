<?php

declare(strict_types=1);

namespace Tests\Support;

use App\DTOs\Content\ArticleCardDTO;
use App\DTOs\Contact\ContactLinkDTO;
use App\DTOs\Settings\EmergencyNoticeDTO;
use App\DTOs\Content\EventCardDTO;
use App\DTOs\Settings\FooterColumnDTO;
use App\DTOs\Settings\FooterSettingsDTO;
use App\DTOs\Homepage\HomepageDTO;
use App\DTOs\Homepage\HomepageSectionDataDTO;
use App\DTOs\Homepage\HomepageSectionDTO;
use App\DTOs\Homepage\HomepageSectionTranslationDTO;
use App\DTOs\Homepage\HomepageStatItemDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\DTOs\Navigation\MenuItemDTO;
use App\DTOs\Navigation\NavigationActionDTO;
use App\DTOs\Navigation\NavigationPayloadDTO;
use App\DTOs\Navigation\NavigationTreeDTO;
use App\DTOs\Seo\PageSeoDTO;
use App\DTOs\Content\ResearchCardDTO;
use App\DTOs\Settings\SocialContactSettingsDTO;
use App\DTOs\Settings\SocialLinkDTO;

trait HomepageBladeTestHelpers
{
    protected static function makeSeo(array $overrides = []): PageSeoDTO
    {
        return new PageSeoDTO(
            locale: $overrides['locale'] ?? 'en',
            title: $overrides['title'] ?? 'SPU Homepage',
            metaDescription: array_key_exists('metaDescription', $overrides) ? $overrides['metaDescription'] : 'University description',
            ogTitle: array_key_exists('ogTitle', $overrides) ? $overrides['ogTitle'] : 'OG Title',
            ogDescription: array_key_exists('ogDescription', $overrides) ? $overrides['ogDescription'] : 'OG Desc',
            ogImage: array_key_exists('ogImage', $overrides) ? $overrides['ogImage'] : 'https://example.com/og.jpg',
            canonicalUrl: array_key_exists('canonicalUrl', $overrides) ? $overrides['canonicalUrl'] : 'https://example.com/en',
            hreflang: $overrides['hreflang'] ?? [
                ['locale' => 'ar', 'url' => 'https://example.com/ar'],
                ['locale' => 'en', 'url' => 'https://example.com/en'],
            ],
            robots: array_key_exists('robots', $overrides) ? $overrides['robots'] : null,
        );
    }

    protected static function makeEmptyNavTree(string $type = 'header', string $locale = 'en'): NavigationTreeDTO
    {
        return new NavigationTreeDTO(treeType: $type, locale: $locale, direction: 'ltr', items: []);
    }

    protected static function makeNavItem(array $overrides = []): MenuItemDTO
    {
        static $counter = 0;
        $counter++;

        return new MenuItemDTO(
            id: $overrides['id'] ?? $counter,
            parentId: null,
            label: $overrides['label'] ?? "Nav Item {$counter}",
            itemType: 'custom_link',
            groupKey: $overrides['groupKey'] ?? 'header',
            targetType: 'url',
            locale: $overrides['locale'] ?? 'en',
            targetId: null,
            url: $overrides['url'] ?? '/en/test',
            resolvedUrl: $overrides['resolvedUrl'] ?? '/en/test',
            target: $overrides['target'] ?? null,
            routeName: null,
            cssToken: null,
            icon: null,
            isActive: $overrides['isActive'] ?? false,
            sortOrder: $overrides['sortOrder'] ?? $counter,
            depth: 0,
            isEnabled: true,
            isUtility: false,
            openInNewTab: $overrides['openInNewTab'] ?? false,
            children: $overrides['children'] ?? [],
        );
    }

    protected static function makeEmergencyNotice(bool $enabled = false, string $locale = 'en'): EmergencyNoticeDTO
    {
        return new EmergencyNoticeDTO(
            locale: $locale,
            isEnabled: $enabled,
            title: $enabled ? 'Emergency Title' : null,
            message: $enabled ? 'Emergency message body' : null,
        );
    }

    protected static function makeFooterSettings(string $locale = 'en', array $overrides = []): FooterSettingsDTO
    {
        return new FooterSettingsDTO(
            locale: $locale,
            copyrightText: $overrides['copyrightText'] ?? '© 2026 SPU',
            brandTitle: $overrides['brandTitle'] ?? 'Syrian Private University',
            brandSummary: $overrides['brandSummary'] ?? 'Excellence in education',
            mapEmbedUrl: $overrides['mapEmbedUrl'] ?? null,
        );
    }

    protected static function makeSocialContact(string $locale = 'en'): SocialContactSettingsDTO
    {
        return new SocialContactSettingsDTO(locale: $locale, socialLinks: [], contactLinks: []);
    }

    protected static function makeNavigation(string $locale = 'en', array $overrides = []): NavigationPayloadDTO
    {
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';

        return new NavigationPayloadDTO(
            locale: $locale,
            direction: $dir,
            header: $overrides['header'] ?? new NavigationTreeDTO('header', $locale, $dir, $overrides['headerItems'] ?? []),
            footer: $overrides['footer'] ?? self::makeEmptyNavTree('footer', $locale),
            utility: $overrides['utility'] ?? self::makeEmptyNavTree('utility', $locale),
            languageSwitchLinks: $overrides['languageSwitchLinks'] ?? [
                new LanguageSwitchLinkDTO('ar', 'العربية', '/ar', $locale === 'ar'),
                new LanguageSwitchLinkDTO('en', 'English', '/en', $locale === 'en'),
            ],
            applyCta: array_key_exists('applyCta', $overrides) ? $overrides['applyCta'] : null,
            studentPortalUrl: array_key_exists('studentPortalUrl', $overrides) ? $overrides['studentPortalUrl'] : null,
            staffAccessUrl: array_key_exists('staffAccessUrl', $overrides) ? $overrides['staffAccessUrl'] : null,
            emergencyNotice: $overrides['emergencyNotice'] ?? self::makeEmergencyNotice(false, $locale),
            footerSettings: $overrides['footerSettings'] ?? self::makeFooterSettings($locale),
            socialContact: $overrides['socialContact'] ?? self::makeSocialContact($locale),
        );
    }

    protected static function makeLanguageSwitch(string $currentLocale = 'en'): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'العربية', '/ar', $currentLocale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'English', '/en', $currentLocale === 'en'),
        ];
    }

    protected static function makeTranslation(string $locale = 'en'): HomepageSectionTranslationDTO
    {
        return new HomepageSectionTranslationDTO(locale: $locale);
    }

    protected static function makeSection(string $key, array $payloadOverrides = [], array $sectionOverrides = []): HomepageSectionDTO
    {
        return new HomepageSectionDTO(
            id: $sectionOverrides['id'] ?? 1,
            key: $key,
            sortOrder: $sectionOverrides['sortOrder'] ?? 1,
            isEnabled: $sectionOverrides['isEnabled'] ?? true,
            payload: new HomepageSectionDataDTO(...$payloadOverrides),
            arabicTranslation: self::makeTranslation('ar'),
            englishTranslation: self::makeTranslation('en'),
        );
    }

    protected static function makeStat(array $overrides = []): HomepageStatItemDTO
    {
        return new HomepageStatItemDTO(
            value: $overrides['value'] ?? '100',
            label: $overrides['label'] ?? 'Test Stat',
            prefix: $overrides['prefix'] ?? null,
            suffix: $overrides['suffix'] ?? '+',
            helperText: $overrides['helperText'] ?? null,
        );
    }

    protected static function makeArticle(array $overrides = []): ArticleCardDTO
    {
        static $artCounter = 0;
        $artCounter++;

        return new ArticleCardDTO(
            id: $overrides['id'] ?? $artCounter,
            locale: $overrides['locale'] ?? 'en',
            title: $overrides['title'] ?? "Article {$artCounter}",
            slug: $overrides['slug'] ?? "article-{$artCounter}",
            excerpt: array_key_exists('excerpt', $overrides) ? $overrides['excerpt'] : 'Article excerpt',
            imageUrl: array_key_exists('imageUrl', $overrides) ? $overrides['imageUrl'] : '/images/test.jpg',
            publishedAt: array_key_exists('publishedAt', $overrides) ? $overrides['publishedAt'] : 'March 15, 2026',
            url: array_key_exists('url', $overrides) ? $overrides['url'] : '/en/news/article',
            categoryLabel: array_key_exists('categoryLabel', $overrides) ? $overrides['categoryLabel'] : 'News',
            badgeTag: array_key_exists('badgeTag', $overrides) ? $overrides['badgeTag'] : null,
        );
    }

    protected static function makeResearchItem(array $overrides = []): ResearchCardDTO
    {
        static $resCounter = 0;
        $resCounter++;

        return new ResearchCardDTO(
            id: $overrides['id'] ?? $resCounter,
            locale: $overrides['locale'] ?? 'en',
            title: $overrides['title'] ?? "Research {$resCounter}",
            slug: $overrides['slug'] ?? "research-{$resCounter}",
            summary: array_key_exists('summary', $overrides) ? $overrides['summary'] : 'Research summary',
            imageUrl: array_key_exists('imageUrl', $overrides) ? $overrides['imageUrl'] : '/images/research.jpg',
            publishedAt: array_key_exists('publishedAt', $overrides) ? $overrides['publishedAt'] : '2026-03-15',
            url: array_key_exists('url', $overrides) ? $overrides['url'] : '/en/research/item',
            categoryLabel: array_key_exists('categoryLabel', $overrides) ? $overrides['categoryLabel'] : 'Medicine',
            authors: $overrides['authors'] ?? ['Dr. Smith', 'Dr. Jones'],
        );
    }

    protected static function makeEvent(array $overrides = []): EventCardDTO
    {
        static $evtCounter = 0;
        $evtCounter++;

        return new EventCardDTO(
            id: $overrides['id'] ?? $evtCounter,
            locale: $overrides['locale'] ?? 'en',
            title: $overrides['title'] ?? "Event {$evtCounter}",
            slug: $overrides['slug'] ?? "event-{$evtCounter}",
            summary: array_key_exists('summary', $overrides) ? $overrides['summary'] : 'Event summary',
            startsAt: array_key_exists('startsAt', $overrides) ? $overrides['startsAt'] : '2026-03-15',
            endsAt: null,
            location: array_key_exists('location', $overrides) ? $overrides['location'] : 'Main Campus',
            url: array_key_exists('url', $overrides) ? $overrides['url'] : '/en/events/item',
            timeLabel: array_key_exists('timeLabel', $overrides) ? $overrides['timeLabel'] : 'Seminar',
        );
    }

    /**
     * Build the full set of view data needed to render layouts.public.
     */
    protected static function makeLayoutData(array $overrides = []): array
    {
        $locale = $overrides['locale'] ?? 'en';
        $direction = $overrides['direction'] ?? ($locale === 'ar' ? 'rtl' : 'ltr');

        return [
            'locale' => $locale,
            'direction' => $direction,
            'seo' => $overrides['seo'] ?? self::makeSeo(['locale' => $locale]),
            'navigation' => $overrides['navigation'] ?? self::makeNavigation($locale),
            'languageSwitch' => $overrides['languageSwitch'] ?? self::makeLanguageSwitch($locale),
            'isPreview' => $overrides['isPreview'] ?? false,
            'preview' => $overrides['preview'] ?? null,
            'homepageFooterSection' => array_key_exists('homepageFooterSection', $overrides) ? $overrides['homepageFooterSection'] : null,
            'settings' => $overrides['settings'] ?? null,
        ];
    }
}
