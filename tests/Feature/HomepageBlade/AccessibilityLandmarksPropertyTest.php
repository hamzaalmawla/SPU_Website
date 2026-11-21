<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class AccessibilityLandmarksPropertyTest extends TestCase
{
    use HomepageBladeTestHelpers;

    public function test_layout_contains_nav_main_and_footer_landmarks(): void
    {
        $data = self::makeLayoutData();
        $html = view('layouts.public', $data)->render();

        $this->assertMatchesRegularExpression('/<nav\s[^>]*aria-label/', $html, 'Expected <nav> with aria-label');
        $this->assertMatchesRegularExpression('/<main(?:\s[^>]*)?>/', $html, 'Expected <main> element');
        $this->assertMatchesRegularExpression('/<footer\s/', $html, 'Expected <footer> element');
    }

    public function test_hero_uses_h1_and_other_sections_use_h2(): void
    {
        $heroSection = self::makeSection('hero', ['title' => 'Hero Heading']);
        $statsSection = self::makeSection('hero_stats', [
            'title' => 'Stats Heading',
            'stats' => [self::makeStat()],
        ], ['sortOrder' => 2]);

        // Hero should have h1
        $heroHtml = view('public.partials.homepage-section', [
            'section' => $heroSection,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('<h1', $heroHtml, 'Hero should use <h1>');
        $this->assertStringNotContainsString('<h2', $heroHtml, 'Hero should not use <h2> for its title');

        // Stats should have h2
        $statsHtml = view('public.partials.homepage-section', [
            'section' => $statsSection,
            'locale' => 'en',
        ])->render();

        // Stats should NOT have h2 (title hidden to match frontend overlap design)
        $this->assertStringNotContainsString('<h2', $statsHtml, 'Stats should not have visible <h2> (matches frontend)');
    }

    public function test_news_cards_use_article_elements(): void
    {
        $section = self::makeSection('university_news', [
            'title' => 'News',
            'articles' => [self::makeArticle(['title' => 'Test Article'])],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('<article', $html, 'News cards should use <article> elements');
    }

    /**
     * Faculty cards are carousel slides. An <article> cannot take role="group"
     * (axe: aria-allowed-role), and Lighthouse's agentic tree audit fails the
     * page on it, so each slide is a <div role="group"> named by its position.
     */
    public function test_faculty_cards_are_labelled_carousel_slides(): void
    {
        $section = self::makeSection('academic_faculties', [
            'title' => 'Faculties',
            'items' => [['title' => 'Medicine', 'image' => '/img.png']],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertMatchesRegularExpression('/<div[^>]*class="faculty-card[^"]*"[^>]*role="group"[^>]*aria-roledescription="[^"]+"[^>]*aria-label="[^"]+"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<article[^>]*role="group"/', $html);
    }
}
