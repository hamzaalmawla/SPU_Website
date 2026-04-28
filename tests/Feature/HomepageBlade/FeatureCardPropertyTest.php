<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class FeatureCardPropertyTest extends TestCase
{
    use HomepageBladeTestHelpers;

    #[DataProvider('featureCardCountProvider')]
    public function test_faculties_section_renders_correct_number_of_cards(int $count): void
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = ['title' => "Faculty {$i}", 'image' => '/img.png'];
        }

        $section = self::makeSection('academic_faculties', [
            'title' => 'Faculties',
            'items' => $items,
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        for ($i = 0; $i < $count; $i++) {
            $this->assertStringContainsString("Faculty {$i}", $html);
        }

        $cardCount = substr_count($html, 'faculty-card');
        $this->assertSame($count, $cardCount, "Expected {$count} faculty cards, got {$cardCount}");
    }

    public function test_feature_card_renders_optional_fields(): void
    {
        $items = [
            [
                'title' => 'Full Card',
                'summary' => 'Card summary text',
                'accent' => '#bc2428',
                'metric' => '6 Years',
                'image' => '/logo.png',
                'action' => ['label' => 'Learn More', 'url' => '/faculty/medicine'],
            ],
        ];

        $section = self::makeSection('academic_faculties', [
            'title' => 'Faculties',
            'items' => $items,
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('Full Card', $html);
        $this->assertStringContainsString('#bc2428', $html);
        $this->assertStringContainsString('6 Years', $html);
        $this->assertStringContainsString('Learn More', $html);
        $this->assertStringContainsString('/faculty/medicine', $html);
    }

    public function test_medical_facilities_renders_main_and_side_cards(): void
    {
        $items = [
            ['title' => 'Main Facility', 'summary' => 'Main desc', 'image' => '/main.jpg'],
            ['title' => 'Hospital', 'summary' => 'Hospital desc', 'image' => '/hospital.jpg'],
            ['title' => 'Dental', 'image' => '/dental.jpg', 'action' => ['label' => 'Explore', 'url' => '/dental']],
        ];

        $section = self::makeSection('medical_facilities_services', [
            'title' => 'Healthcare',
            'items' => $items,
            'stats' => [self::makeStat(['value' => '200', 'label' => 'Beds'])],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('Main Facility', $html);
        $this->assertStringContainsString('Hospital', $html);
        $this->assertStringContainsString('Dental', $html);
        $this->assertStringContainsString('Beds', $html);
    }

    public static function featureCardCountProvider(): array
    {
        return array_map(fn ($n) => [$n], range(0, 7));
    }
}
