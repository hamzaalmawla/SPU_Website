<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Filament\Support\ImageDimensionGuide;
use Tests\TestCase;

final class ImageDimensionGuideTest extends TestCase
{
    public function test_detects_hero_profile(): void
    {
        $this->assertSame('hero', ImageDimensionGuide::detectProfile('hero_image'));
        $this->assertSame('hero', ImageDimensionGuide::detectProfile('ar.hero.backgroundImage'));
        $this->assertSame('hero', ImageDimensionGuide::detectProfile('imagePrimary'));
        $this->assertSame('hero', ImageDimensionGuide::detectProfile('imageSecondary'));
        $this->assertSame('hero', ImageDimensionGuide::detectProfile('imageOverview'));
        $this->assertSame('hero', ImageDimensionGuide::detectProfile('founding_image'));
    }

    public function test_detects_portrait_profile(): void
    {
        $this->assertSame('portrait', ImageDimensionGuide::detectProfile('photo_media_id'));
        $this->assertSame('portrait', ImageDimensionGuide::detectProfile('manager_photo'));
        $this->assertSame('portrait', ImageDimensionGuide::detectProfile('speaker_photo'));
        $this->assertSame('portrait', ImageDimensionGuide::detectProfile('dean_image'));
        $this->assertSame('portrait', ImageDimensionGuide::detectProfile('profile_image'));
    }

    public function test_detects_social_profile(): void
    {
        $this->assertSame('social', ImageDimensionGuide::detectProfile('og_image'));
        $this->assertSame('social', ImageDimensionGuide::detectProfile('seoImage'));
        $this->assertSame('social', ImageDimensionGuide::detectProfile('seo_image'));
        $this->assertSame('social', ImageDimensionGuide::detectProfile('prefix.seo.image'));
    }

    public function test_detects_icon_profile(): void
    {
        $this->assertSame('icon', ImageDimensionGuide::detectProfile('icon', 'icon'));
        $this->assertSame('icon', ImageDimensionGuide::detectProfile('logo'));
        $this->assertSame('icon', ImageDimensionGuide::detectProfile('faculty.logoImage'));
        $this->assertSame('icon', ImageDimensionGuide::detectProfile('phone_icon'));
    }

    public function test_detects_card_profile(): void
    {
        $this->assertSame('card', ImageDimensionGuide::detectProfile('cover_media_id'));
        $this->assertSame('card', ImageDimensionGuide::detectProfile('article_image'));
        $this->assertSame('card', ImageDimensionGuide::detectProfile('event_image'));
        $this->assertSame('card', ImageDimensionGuide::detectProfile('center_image'));
        $this->assertSame('card', ImageDimensionGuide::detectProfile('project_image'));
    }

    public function test_detects_gallery_profile(): void
    {
        $this->assertSame('gallery', ImageDimensionGuide::detectProfile('gallery.images'));
        $this->assertSame('gallery', ImageDimensionGuide::detectProfile('tour.scenes'));
        $this->assertSame('gallery', ImageDimensionGuide::detectProfile('scene_image'));
        $this->assertSame('gallery', ImageDimensionGuide::detectProfile('timeline_image'));
    }

    public function test_defaults_to_general_for_unmatched(): void
    {
        $this->assertSame('general', ImageDimensionGuide::detectProfile('custom_upload'));
        $this->assertSame('general', ImageDimensionGuide::detectProfile('file'));
    }

    public function test_returns_localized_data_in_arabic_and_english(): void
    {
        app()->setLocale('ar');
        $heroAr = ImageDimensionGuide::forSlot('hero_image');
        $this->assertStringContainsString('1920', $heroAr['dimensions']);
        $this->assertStringContainsString('16:9', $heroAr['ratio']);
        $this->assertNotEmpty($heroAr['name']);
        $this->assertNotEmpty($heroAr['tip']);
        $this->assertStringContainsString('1920', $heroAr['compact_hint']);

        app()->setLocale('en');
        $heroEn = ImageDimensionGuide::forSlot('hero_image');
        $this->assertStringContainsString('1920', $heroEn['dimensions']);
        $this->assertStringContainsString('16:9', $heroEn['ratio']);
        $this->assertNotEmpty($heroEn['name']);
        $this->assertNotEmpty($heroEn['tip']);
        $this->assertStringContainsString('1920', $heroEn['compact_hint']);
    }

    public function test_compact_badge_html_renders_html_string(): void
    {
        $badge = ImageDimensionGuide::compactBadgeHtml('hero_image');
        $this->assertStringContainsString('spu-image-hint-pill', (string) $badge);
        $this->assertStringContainsString('1920', (string) $badge);
    }

    public function test_all_profiles_returns_every_configured_profile(): void
    {
        $profiles = ImageDimensionGuide::allProfiles();
        $this->assertCount(7, $profiles);

        $keys = array_column($profiles, 'key');
        $this->assertContains('hero', $keys);
        $this->assertContains('card', $keys);
        $this->assertContains('portrait', $keys);
        $this->assertContains('social', $keys);
        $this->assertContains('icon', $keys);
        $this->assertContains('gallery', $keys);
        $this->assertContains('general', $keys);
    }
}
