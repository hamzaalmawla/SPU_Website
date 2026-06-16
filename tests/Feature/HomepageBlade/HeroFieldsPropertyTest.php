<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use App\DTOs\Navigation\NavigationActionDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class HeroFieldsPropertyTest extends TestCase
{
    use HomepageBladeTestHelpers;

    #[DataProvider('heroPayloadCombinations')]
    public function test_hero_renders_non_null_fields_only(array $payload, array $expectedPresent, array $expectedAbsent): void
    {
        $section = self::makeSection('hero', $payload);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        foreach ($expectedPresent as $text) {
            $this->assertStringContainsString($text, $html, "Expected '{$text}' in hero output");
        }

        foreach ($expectedAbsent as $text) {
            $this->assertStringNotContainsString($text, $html, "Did not expect '{$text}' in hero output");
        }
    }

    public static function heroPayloadCombinations(): array
    {
        return [
            'all fields populated' => [
                [
                    'title' => 'Hero Title',
                    'eyebrow' => 'Eyebrow Text',
                    'badge' => 'Badge Text',
                    'subtitle' => 'Subtitle Text',
                    'summary' => 'Summary Text',
                    'primaryAction' => new NavigationActionDTO('Primary CTA', '/primary'),
                    'secondaryAction' => new NavigationActionDTO('Secondary CTA', '/secondary'),
                ],
                ['Hero Title', 'Eyebrow Text', 'Badge Text', 'Subtitle Text', 'Summary Text', 'Primary CTA', 'Secondary CTA'],
                [],
            ],
            'only title' => [
                ['title' => 'Only Title'],
                ['Only Title'],
                ['home-hero__eyebrow', 'home-hero__badge'],
            ],
            'title + eyebrow, no CTAs' => [
                ['title' => 'Title With Eyebrow', 'eyebrow' => 'My Eyebrow'],
                ['Title With Eyebrow', 'My Eyebrow'],
                ['home-hero__primary-btn', 'home-hero__secondary-btn'],
            ],
            'summary preferred over body' => [
                ['title' => 'Title', 'summary' => 'Summary Wins', 'body' => 'Body Loses'],
                ['Summary Wins'],
                ['Body Loses'],
            ],
            'body used when summary null' => [
                ['title' => 'Title', 'body' => 'Body Fallback'],
                ['Body Fallback'],
                [],
            ],
        ];
    }
}
