<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyUrlNormalizerInterface;
use Tests\TestCase;

final class LegacyUrlNormalizerTest extends TestCase
{
    private LegacyUrlNormalizerInterface $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = app(LegacyUrlNormalizerInterface::class);
    }

    public function test_normalizes_root_item_show_query_aliases(): void
    {
        $normalized = $this->normalizer->normalize('/index.php', 'page=show&ex=2&dir=items&lang=2&ser=4&cat_id=123&act=123');

        $this->assertSame('/index.php', $normalized->path);
        $this->assertSame('root', $normalized->subsite->key);
        $this->assertSame(0, $normalized->subsite->siteId);
        $this->assertSame(2, $normalized->language->oldLanguageId);
        $this->assertSame('en', $normalized->language->locale);
        $this->assertSame('items', $normalized->dir);
        $this->assertSame('show', $normalized->page);
        $this->assertSame('php', $normalized->extension);
        $this->assertSame('4', $normalized->service);
        $this->assertSame('root:items:show', $normalized->handlerKey);
        $this->assertSame('legacy_router', $normalized->requestType);
    }

    public function test_normalizes_members_council_show_defaults_missing_extension(): void
    {
        $normalized = $this->normalizer->normalize('/members/index.php', 'page=show&dir=councils&service=6&council_id=123&lang=1');

        $this->assertSame('members', $normalized->subsite->key);
        $this->assertSame(13, $normalized->subsite->siteId);
        $this->assertSame('ar', $normalized->language->locale);
        $this->assertSame('php', $normalized->extension);
        $this->assertSame('members:councils:show', $normalized->handlerKey);
    }

    public function test_normalizes_mylang_and_capital_service_alias(): void
    {
        $normalized = $this->normalizer->normalize('/index.php', 'mylang=&page=list&dir=photos&Ser=8');

        $this->assertSame(1, $normalized->language->oldLanguageId);
        $this->assertSame('ar', $normalized->language->locale);
        $this->assertSame('8', $normalized->service);
        $this->assertSame('root:photos:list', $normalized->handlerKey);
    }

    public function test_preserves_unsupported_old_language_with_arabic_fallback(): void
    {
        $normalized = $this->normalizer->normalize('/index.php', 'lang=3&page=list&dir=items&service=4');

        $this->assertSame(3, $normalized->language->oldLanguageId);
        $this->assertSame('fr', $normalized->language->oldSymbol);
        $this->assertFalse($normalized->language->isSupportedLocale);
        $this->assertSame('ar', $normalized->language->locale);
        $this->assertSame('ar', $normalized->language->fallbackLocale);
    }

    public function test_identifies_public_admin_subsite_without_confusing_it_with_laravel_admin(): void
    {
        $normalized = $this->normalizer->normalize('/admin/index.php', 'page=list&dir=items&service=73&lang=1');

        $this->assertSame('admin', $normalized->subsite->key);
        $this->assertSame(7, $normalized->subsite->siteId);
        $this->assertTrue($normalized->subsite->isPublicAdminSubsite);
        $this->assertSame('admin:items:list', $normalized->handlerKey);
    }

    public function test_identifies_direct_legacy_media_file_requests(): void
    {
        $normalized = $this->normalizer->normalize('/downloads/files/example.pdf');

        $this->assertSame('legacy_media_file', $normalized->requestType);
        $this->assertSame('legacy_media_file', $normalized->handlerKey);
    }

    public function test_normalizes_category_and_show_page_aliases(): void
    {
        $normalized = $this->normalizer->normalize('/index.php', 'page=show_cat&dir=items&ser=3&cat=77&lang=2');

        $this->assertSame('show', $normalized->page);
        $this->assertSame('3', $normalized->service);
        $this->assertSame('77', $normalized->params['cat_id']);
        $this->assertSame('root:items:show', $normalized->handlerKey);
    }

    public function test_identifies_dental_clinic_subsite(): void
    {
        $normalized = $this->normalizer->normalize('/dent_clinic/index.php', 'lang=1');

        $this->assertSame('dent_clinic', $normalized->subsite->key);
        $this->assertSame(10, $normalized->subsite->siteId);
        $this->assertSame('dent_clinic:home', $normalized->handlerKey);
    }
}
