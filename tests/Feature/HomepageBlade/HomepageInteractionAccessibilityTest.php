<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class HomepageInteractionAccessibilityTest extends TestCase
{
    use HomepageBladeTestHelpers;

    public function test_counters_render_their_real_values_without_javascript(): void
    {
        foreach (['hero_stats', 'bottom_stats', 'medical_facilities_services'] as $key) {
            $section = self::makeSection($key, [
                'title' => 'Statistics',
                'stats' => [self::makeStat(['value' => '1250'])],
            ]);

            $html = view('public.partials.homepage-section', [
                'section' => $section,
                'locale' => 'en',
            ])->render();

            $this->assertMatchesRegularExpression('/data-value="1250"[^>]*>1250<\/span>/', $html);
        }
    }

    public function test_homepage_sliders_expose_keyboard_and_localized_carousel_markup(): void
    {
        app()->setLocale('en');

        $section = self::makeSection('academic_faculties', [
            'title' => 'Academic Faculties',
            'items' => [['title' => 'Medicine']],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('aria-roledescription="carousel"', $html);
        $this->assertStringContainsString('aria-label="Slide 1 of 1"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('@keydown="handleSliderKey($event)"', $html);
        $this->assertStringContainsString('aria-controls="academic-faculties-track"', $html);
    }

    public function test_honor_secondary_panels_and_path_actions_are_keyboard_operable(): void
    {
        $honor = self::makeSection('achievements_highlights', [
            'title' => 'Achievements',
            'items' => [['id' => 1, 'title' => 'Achievement', 'image' => '/images/slider-1.webp']],
        ]);
        $path = self::makeSection('choose_your_path', [
            'title' => 'Choose Your Path',
            'items' => [[
                'title' => 'Student',
                'action' => ['label' => 'Explore', 'url' => '/en/admissions'],
            ]],
        ]);

        $honorHtml = view('public.partials.homepage-section', ['section' => $honor, 'locale' => 'en'])->render();
        $pathHtml = view('public.partials.homepage-section', ['section' => $path, 'locale' => 'en'])->render();

        $this->assertStringContainsString('x-show="isSecondary(index)" type="button"', $honorHtml);
        $this->assertStringContainsString(':aria-hidden="isHidden(index)"', $honorHtml);
        $this->assertStringContainsString('group-focus-within:translate-y-0', $pathHtml);
        $this->assertStringContainsString('href="/en/admissions"', $pathHtml);
    }

    public function test_static_homepage_assets_exist(): void
    {
        $assets = [];

        foreach (glob(resource_path('views/public/home/sections/*.blade.php')) ?: [] as $view) {
            $contents = (string) file_get_contents($view);
            preg_match_all('#/images/[A-Za-z0-9_./-]+\.(?:svg|png|jpe?g|webp)#i', $contents, $matches);
            $assets = [...$assets, ...$matches[0]];
        }

        $assets = array_values(array_unique($assets));
        $this->assertNotEmpty($assets);

        foreach ($assets as $asset) {
            $this->assertFileExists(public_path(ltrim($asset, '/')), "Missing homepage asset: {$asset}");
        }
    }
}
