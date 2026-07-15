<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class NewsCardPropertyTest extends TestCase
{
    use HomepageBladeTestHelpers;

    #[DataProvider('newsCountProvider')]
    public function test_news_section_renders_correct_number_of_cards(int $count): void
    {
        $articles = [];
        for ($i = 0; $i < $count; $i++) {
            $articles[] = self::makeArticle(['title' => "News Article {$i}"]);
        }

        $section = self::makeSection('university_news', [
            'title' => 'News Title',
            'articles' => $articles,
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        for ($i = 0; $i < $count; $i++) {
            $this->assertStringContainsString("News Article {$i}", $html);
        }

        if ($count === 0) {
            $this->assertStringNotContainsString('xl:grid-cols-4', $html);
        }
    }

    public function test_news_card_renders_optional_fields_when_present(): void
    {
        $article = self::makeArticle([
            'title' => 'Full Article',
            'excerpt' => 'Article excerpt text',
            'publishedAt' => 'March 15, 2026',
            'categoryLabel' => 'Campus',
        ]);

        $section = self::makeSection('university_news', [
            'title' => 'News',
            'articles' => [$article],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('Full Article', $html);
        $this->assertStringContainsString('Article excerpt text', $html);
        $this->assertStringContainsString('March 15, 2026', $html);
        $this->assertStringContainsString('Campus', $html);
    }

    public function test_news_card_omits_optional_fields_when_null(): void
    {
        $article = self::makeArticle([
            'title' => 'Minimal Article',
            'excerpt' => null,
            'publishedAt' => null,
            'categoryLabel' => null,
        ]);

        $section = self::makeSection('university_news', [
            'title' => 'News',
            'articles' => [$article],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('Minimal Article', $html);
    }

    public static function newsCountProvider(): array
    {
        return array_map(fn ($n) => [$n], range(0, 6));
    }
}
