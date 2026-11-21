<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Many legacy papers exist in one language only, or have a field filled in one language only.
 * Every paper and every field it has must show on both the Arabic and the English site.
 */
final class ResearchLocaleFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_arabic_only_paper_shows_on_the_english_archive_and_page(): void
    {
        $this->publication('arabic-only-paper', ['ar' => ['title' => 'دراسة وظائف الرئة', 'abstract' => 'ملخص البحث بالعربية']]);

        $this->get('/en/research/publications')->assertOk()->assertSee('دراسة وظائف الرئة');
        $this->get('/en/research/publications/arabic-only-paper')
            ->assertOk()
            ->assertSee('دراسة وظائف الرئة')
            ->assertSee('ملخص البحث بالعربية')
            ->assertSee('dir="auto"', false);
    }

    public function test_a_field_missing_in_one_language_comes_from_the_other(): void
    {
        $this->publication('half-translated', [
            'ar' => ['title' => 'بحث نصف مترجم', 'abstract' => null, 'authors' => null],
            'en' => ['title' => 'Half Translated', 'abstract' => 'An English only abstract.', 'authors' => 'A. Author'],
        ]);

        $this->get('/ar/research/publications/half-translated')
            ->assertOk()
            ->assertSee('بحث نصف مترجم')
            ->assertSee('An English only abstract.')
            ->assertSee('A. Author');
    }

    public function test_the_archive_search_finds_a_paper_by_its_title_in_the_other_language(): void
    {
        $this->publication('two-titles', [
            'ar' => ['title' => 'نشر بحث علمي جديد بعنوان :  "Automated Detection"'],
            'en' => ['title' => 'Publication of a new scientific research paper entitled: "Automated Detection"'],
        ]);

        $this->get('/en/research/publications?q='.urlencode('نشر بحث علمي جديد بعنوان : "Automated'))
            ->assertOk()
            ->assertSee('Publication of a new scientific research paper entitled');
        $this->get('/ar/research/publications?q='.urlencode('Publication of a new scientific'))
            ->assertOk()
            ->assertSee('نشر بحث علمي جديد بعنوان');
    }

    /** @param array<string, array<string, ?string>> $translations */
    private function publication(string $slug, array $translations): void
    {
        $publication = ResearchPublication::query()->create([
            'slug' => $slug,
            'published_at' => now()->subDay(),
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        foreach ($translations as $locale => $fields) {
            ResearchPublicationTranslation::query()->create(['research_publication_id' => $publication->getKey(), 'locale' => $locale, ...$fields]);
        }
    }
}
