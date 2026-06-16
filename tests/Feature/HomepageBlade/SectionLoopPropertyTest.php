<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use App\DTOs\Homepage\HomepageDTO;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class SectionLoopPropertyTest extends TestCase
{
    use HomepageBladeTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[DataProvider('sectionConfigurations')]
    public function test_section_loop_renders_only_enabled_non_footer_sections(array $sectionDefs, array $expectedKeys): void
    {
        $sections = [];
        foreach ($sectionDefs as $def) {
            $sections[] = self::makeSection($def['key'], [
                'title' => 'Section-'.$def['key'],
            ], [
                'sortOrder' => $def['sortOrder'],
                'isEnabled' => $def['isEnabled'],
            ]);
        }

        // Use HTTP test to get full layout rendering
        // Instead, test the section loop logic directly by rendering the home view
        // with all required layout data
        $homepage = new HomepageDTO(locale: 'en', direction: 'ltr', sections: $sections);

        $data = array_merge(self::makeLayoutData(), [
            'homepage' => $homepage,
        ]);

        $html = view('public.home', $data)->render();

        // Extract main content area
        preg_match('/<main>(.*?)<\/main>/s', $html, $mainMatch);
        $mainContent = $mainMatch[1] ?? '';

        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString('Section-'.$key, $mainContent, "Expected section '{$key}' to be rendered in main content");
        }

        // Footer should never appear in main content
        $this->assertStringNotContainsString('Section-footer', $mainContent);

        // Disabled sections should not appear in the main content area
        // Extract only the <main> content to avoid false positives from layout
        preg_match('/<main>(.*?)<\/main>/s', $html, $mainMatch);
        $mainContent = $mainMatch[1] ?? '';

        foreach ($sectionDefs as $def) {
            if (! $def['isEnabled']) {
                // Use exact title match to avoid substring issues (e.g. Section-hero matching Section-hero_stats)
                $marker = '>Section-'.$def['key'].'<';
                $this->assertStringNotContainsString($marker, $mainContent, "Disabled section '{$def['key']}' should not appear in main content");
            }
            if ($def['key'] === 'footer') {
                $this->assertStringNotContainsString('Section-footer', $mainContent, 'Footer section should not appear in main content');
            }
        }
    }

    public static function sectionConfigurations(): array
    {
        return [
            'all enabled, footer skipped' => [
                [
                    ['key' => 'hero', 'sortOrder' => 1, 'isEnabled' => true],
                    ['key' => 'hero_stats', 'sortOrder' => 2, 'isEnabled' => true],
                    ['key' => 'footer', 'sortOrder' => 10, 'isEnabled' => true],
                ],
                ['hero'],  // hero_stats title is hidden, but section renders — just check hero
            ],
            'mixed enabled/disabled' => [
                [
                    ['key' => 'hero', 'sortOrder' => 1, 'isEnabled' => true],
                    ['key' => 'university_news', 'sortOrder' => 2, 'isEnabled' => false],
                    ['key' => 'research_studies', 'sortOrder' => 3, 'isEnabled' => true],
                ],
                ['hero', 'research_studies'],
            ],
            'empty sections' => [
                [],
                [],
            ],
            'only footer' => [
                [
                    ['key' => 'footer', 'sortOrder' => 1, 'isEnabled' => true],
                ],
                [],
            ],
            'all disabled' => [
                [
                    ['key' => 'hero', 'sortOrder' => 1, 'isEnabled' => false],
                    ['key' => 'hero_stats', 'sortOrder' => 2, 'isEnabled' => false],
                ],
                [],
            ],
        ];
    }
}
