<?php

declare(strict_types=1);

namespace Tests\Feature\PX08;

use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use App\Models\LegacyExactRedirect;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for launch:validate command.
 *
 * Requirements: 34.1
 */
class LaunchValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_launch_validate_passes_with_valid_seeded_data(): void
    {
        $this->seedValidData();

        $this->artisan('launch:validate')
            ->assertSuccessful();
    }

    public function test_launch_validate_reports_failures_with_no_data(): void
    {
        // With no homepage sections, the homepage rendering check should fail
        $this->artisan('launch:validate')
            ->assertFailed();
    }

    public function test_launch_validate_accepts_environment_option(): void
    {
        $this->seedValidData();

        $this->artisan('launch:validate', ['--environment' => 'production'])
            ->assertSuccessful();
    }

    public function test_launch_validate_continues_all_checks_even_on_failure(): void
    {
        // Even with no data, the command should run all checks and report
        $this->artisan('launch:validate')
            ->expectsOutputToContain('Launch Validation Summary')
            ->assertFailed();
    }

    public function test_launch_validate_checks_cache_behavior(): void
    {
        $this->seedValidData();

        // Cache check should pass since array cache driver is functional
        $this->artisan('launch:validate')
            ->expectsOutputToContain('Cache behavior')
            ->assertSuccessful();
    }

    public function test_launch_validate_checks_audit_behavior(): void
    {
        $this->seedValidData();

        $this->artisan('launch:validate')
            ->expectsOutputToContain('Audit behavior')
            ->assertSuccessful();
    }

    /**
     * Seed minimum valid data for launch validation to pass.
     */
    private function seedValidData(): void
    {
        // Create homepage shell page
        $homepagePage = Page::create([
            'slug' => 'homepage',
            'type' => 'homepage',
            'template' => 'homepage',
            'status' => 'published',
            'sort_order' => 0,
            'is_enabled' => true,
            'is_homepage_shell' => true,
            'published_at' => now(),
        ]);

        // Create homepage sections with translations for both locales
        $sectionKeys = [
            'hero', 'hero_stats', 'academic_faculties', 'achievements_highlights',
            'choose_your_path', 'university_news', 'research_studies', 'events_activities',
            'medical_facilities_services', 'bottom_stats', 'footer',
        ];

        foreach ($sectionKeys as $index => $key) {
            $section = HomepageSection::create([
                'key' => $key,
                'type' => 'section',
                'sort_order' => $index,
                'is_enabled' => true,
            ]);

            foreach (['ar', 'en'] as $locale) {
                HomepageSectionTranslation::create([
                    'section_id' => $section->id,
                    'locale' => $locale,
                    'payload_json' => [
                        'headline' => $locale === 'ar' ? 'عنوان' : 'Headline',
                    ],
                ]);
            }
        }

        // Create a published landing page
        $page = Page::create([
            'slug' => 'about',
            'type' => 'landing',
            'template' => 'default',
            'status' => 'published',
            'sort_order' => 1,
            'is_enabled' => true,
            'published_at' => now(),
        ]);

        foreach (['ar', 'en'] as $locale) {
            PageTranslation::create([
                'page_id' => $page->id,
                'locale' => $locale,
                'title' => $locale === 'ar' ? 'حول الجامعة' : 'About University',
                'body' => $locale === 'ar' ? 'محتوى' : 'Content',
            ]);
        }

        // Create a valid redirect rule
        LegacyExactRedirect::create([
            'legacy_path' => '/old-about',
            'destination_url' => '/ar/about',
            'status_code' => 301,
            'is_active' => true,
        ]);
    }
}
