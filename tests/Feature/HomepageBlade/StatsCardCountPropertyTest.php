<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class StatsCardCountPropertyTest extends TestCase
{
    use HomepageBladeTestHelpers;

    #[DataProvider('statsCountProvider')]
    public function test_stats_section_renders_correct_number_of_cards(int $count): void
    {
        $stats = [];
        for ($i = 0; $i < $count; $i++) {
            $stats[] = self::makeStat(['value' => (string) (($i + 1) * 100), 'label' => "Stat {$i}"]);
        }

        $section = self::makeSection('hero_stats', [
            'title' => 'Stats Title',
            'stats' => $stats,
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $cardCount = substr_count($html, 'class="stats-card"');
        $this->assertSame($count, $cardCount, "Expected {$count} stats cards, got {$cardCount}");

        if ($count === 0) {
            $this->assertStringNotContainsString('stats-shell__grid', $html);
        } else {
            $this->assertStringContainsString('stats-shell__grid', $html);
        }
    }

    public function test_bottom_stats_is_not_rendered_because_it_duplicates_the_medical_stats(): void
    {
        $section = self::makeSection('bottom_stats', [
            'title' => 'Additional Figures',
            'stats' => [
                self::makeStat(['value' => '200', 'label' => 'Hospital Beds', 'suffix' => '+']),
                self::makeStat(['value' => '80', 'label' => 'Specialist Doctors', 'suffix' => '+']),
            ],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertSame('', trim($html));
    }

    public static function statsCountProvider(): array
    {
        return array_map(fn ($n) => [$n], range(0, 8));
    }
}
