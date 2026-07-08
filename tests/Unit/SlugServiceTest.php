<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\SlugServiceInterface;
use App\Models\Page\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for SlugService — slug generation, Arabic transliteration,
 * uniqueness with collision suffix, and max attempts exception.
 *
 * Validates: Requirements 31.1
 */
class SlugServiceTest extends TestCase
{
    use RefreshDatabase;

    private SlugServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SlugServiceInterface::class);
    }

    public function test_generates_slug_from_english_text(): void
    {
        $slug = $this->service->generate('Hello World', Page::class, 'en');

        $this->assertSame('hello-world', $slug);
    }

    public function test_generates_slug_from_arabic_text(): void
    {
        $slug = $this->service->generate('جامعة سورية', Page::class, 'ar');

        $this->assertNotEmpty($slug);
        // Should be transliterated to ASCII
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug);
    }

    public function test_transliterates_arabic_characters_to_latin(): void
    {
        $slug = $this->service->generate('بكالوريوس', Page::class, 'ar');

        $this->assertNotEmpty($slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug);
        // Should not contain any Arabic characters
        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $slug);
    }

    public function test_generates_unique_slug_with_collision_suffix(): void
    {
        // Create a page with slug 'test-page'
        Page::create([
            'slug' => 'test-page',
            'type' => 'landing',
            'template' => 'default',
            'status' => 'published',
            'sort_order' => 0,
            'is_enabled' => true,
        ]);

        $slug = $this->service->generate('Test Page', Page::class, 'en');

        $this->assertSame('test-page-1', $slug);
    }

    public function test_generates_incremental_suffix_on_multiple_collisions(): void
    {
        // Create pages with slug 'my-page', 'my-page-1', 'my-page-2'
        foreach (['my-page', 'my-page-1', 'my-page-2'] as $existingSlug) {
            Page::create([
                'slug' => $existingSlug,
                'type' => 'landing',
                'template' => 'default',
                'status' => 'published',
                'sort_order' => 0,
                'is_enabled' => true,
            ]);
        }

        $slug = $this->service->generate('My Page', Page::class, 'en');

        $this->assertSame('my-page-3', $slug);
    }

    public function test_ignores_own_id_when_checking_uniqueness(): void
    {
        $page = Page::create([
            'slug' => 'existing-page',
            'type' => 'landing',
            'template' => 'default',
            'status' => 'published',
            'sort_order' => 0,
            'is_enabled' => true,
        ]);

        // When ignoring own ID, the same slug should be returned
        $slug = $this->service->generate('Existing Page', Page::class, 'en', $page->id);

        $this->assertSame('existing-page', $slug);
    }

    public function test_throws_exception_after_max_collision_attempts(): void
    {
        // Create pages with slug 'overflow' and 'overflow-1' through 'overflow-10'
        Page::create([
            'slug' => 'overflow',
            'type' => 'landing',
            'template' => 'default',
            'status' => 'published',
            'sort_order' => 0,
            'is_enabled' => true,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Page::create([
                'slug' => "overflow-{$i}",
                'type' => 'landing',
                'template' => 'default',
                'status' => 'published',
                'sort_order' => 0,
                'is_enabled' => true,
            ]);
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to generate a unique slug');

        $this->service->generate('Overflow', Page::class, 'en');
    }

    public function test_empty_source_produces_untitled_slug(): void
    {
        $slug = $this->service->generate('', Page::class, 'en');

        $this->assertSame('untitled', $slug);
    }

    public function test_auto_detects_arabic_in_english_locale(): void
    {
        // Even with 'en' locale, Arabic text should be transliterated
        $slug = $this->service->generate('كلية الطب', Page::class, 'en');

        $this->assertNotEmpty($slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug);
    }

    public function test_custom_max_length_applies_to_collision_suffixes(): void
    {
        $source = 'Syrian Private University announces important registration dates for newly admitted students';
        $first = $this->service->generate($source, Page::class, 'en', null, 30);

        Page::create([
            'slug' => $first,
            'type' => 'landing',
            'template' => 'default',
            'status' => 'published',
            'sort_order' => 0,
            'is_enabled' => true,
        ]);

        $second = $this->service->generate($source, Page::class, 'en', null, 30);

        $this->assertLessThanOrEqual(30, strlen($first));
        $this->assertLessThanOrEqual(30, strlen($second));
        $this->assertStringEndsWith('-1', $second);
    }
}
