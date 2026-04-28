<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class ResearchCardPropertyTest extends TestCase
{
    use HomepageBladeTestHelpers;

    #[DataProvider('researchCountProvider')]
    public function test_research_section_renders_correct_number_of_cards(int $count): void
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = self::makeResearchItem(['title' => "Research Item {$i}"]);
        }

        $section = self::makeSection('research_studies', [
            'title' => 'Research Title',
            'researchItems' => $items,
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        for ($i = 0; $i < $count; $i++) {
            $this->assertStringContainsString("Research Item {$i}", $html);
        }
    }

    public function test_research_card_renders_authors_when_present(): void
    {
        $item = self::makeResearchItem([
            'title' => 'Authored Research',
            'authors' => ['Dr. Alpha', 'Dr. Beta'],
        ]);

        $section = self::makeSection('research_studies', [
            'title' => 'Research',
            'researchItems' => [$item],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('Dr. Alpha', $html);
        $this->assertStringContainsString('Dr. Beta', $html);
    }

    public static function researchCountProvider(): array
    {
        return array_map(fn ($n) => [$n], range(0, 5));
    }
}
