<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Seo\SitemapServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\Faculty\FacultyStudentProjectTranslation;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\PropertyTestHelpers;
use Tests\TestCase;

/**
 * Property-based tests for SitemapService.
 *
 * Feature: spu-homepage-admin-foundation
 */
#[Group('property')]
class SitemapServiceTest extends TestCase
{
    use PropertyTestHelpers;
    use RefreshDatabase;

    public function test_enabled_faculty_project_details_are_included_with_locale_alternates(): void
    {
        $faculty = Faculty::query()->create([
            'slug' => 'pharmacy',
            'public_slug' => 'pharmacy',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $project = FacultyStudentProject::query()->create([
            'faculty_id' => (int) $faculty->getKey(),
            'slug' => 'pharmacy-project-6147',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        foreach (['ar', 'en'] as $locale) {
            FacultyStudentProjectTranslation::query()->create([
                'faculty_student_project_id' => (int) $project->getKey(),
                'locale' => $locale,
                'title' => 'Project',
            ]);
        }

        $entries = app(SitemapServiceInterface::class)->generateSectionEntries('faculties');

        self::assertTrue($entries->contains(fn ($entry): bool => str_ends_with($entry->loc, '/ar/faculties/pharmacy/projects/pharmacy-project-6147')));
        self::assertTrue($entries->contains(fn ($entry): bool => str_ends_with($entry->loc, '/en/faculties/pharmacy/projects/pharmacy-project-6147')));
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 4: Sitemap contains only published, enabled pages
    // Feature: spu-homepage-admin-foundation, Property 4: Sitemap contains only published, enabled pages
    // **Validates: Requirements 16.1, 16.2**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: list<array{slug: string, type: string, template: string, status: string, is_enabled: bool, is_homepage_shell: bool, published_at: ?string, locales: list<string>}>}>
     */
    public static function pageCollectionProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 100; $i++) {
            $cases["iteration_{$i}"] = [self::randomPageCollection()];
        }

        return $cases;
    }

    /**
     * @param  list<array{slug: string, type: string, template: string, status: string, is_enabled: bool, is_homepage_shell: bool, published_at: ?string, locales: list<string>}>  $pageSpecs
     */
    #[DataProvider('pageCollectionProvider')]
    public function test_sitemap_contains_only_published_enabled_pages(array $pageSpecs): void
    {
        // Seed the database with the random page collection
        $createdPages = [];
        foreach ($pageSpecs as $spec) {
            $page = Page::create([
                'slug' => $spec['slug'],
                'type' => $spec['type'],
                'template' => $spec['template'],
                'status' => $spec['status'],
                'is_enabled' => $spec['is_enabled'],
                'is_homepage_shell' => $spec['is_homepage_shell'],
                'published_at' => $spec['published_at'],
            ]);

            foreach ($spec['locales'] as $locale) {
                PageTranslation::create([
                    'page_id' => $page->id,
                    'locale' => $locale,
                    'title' => 'Test Page '.$spec['slug'].' '.$locale,
                ]);
            }

            $createdPages[] = ['page' => $page, 'spec' => $spec];
        }

        // Generate sitemap entries
        $sitemapService = app(SitemapServiceInterface::class);
        $entries = $sitemapService->generateEntries();

        // Determine which pages should appear in the sitemap
        $expectedSlugs = [];
        foreach ($pageSpecs as $spec) {
            if (
                $spec['status'] === 'published'
                && $spec['is_enabled'] === true
                && $spec['published_at'] !== null
            ) {
                $expectedSlugs[] = $spec['slug'];
            }
        }

        $expectedEntryCount = 0;
        foreach ($pageSpecs as $spec) {
            if (
                $spec['status'] === 'published'
                && $spec['is_enabled'] === true
                && $spec['published_at'] !== null
            ) {
                $expectedEntryCount += count($spec['locales']);
            }
        }

        $this->assertCount($expectedEntryCount, $entries);

        // Verify: every entry in the sitemap corresponds to a valid published+enabled page
        foreach ($entries as $entry) {
            $matchesExpected = false;
            foreach ($expectedSlugs as $slug) {
                if (str_contains($entry->loc, $slug)) {
                    $matchesExpected = true;
                    break;
                }
            }

            $this->assertTrue(
                $matchesExpected,
                "Sitemap entry {$entry->loc} does not correspond to a published+enabled page with published_at set"
            );
        }

        // Verify: no draft, disabled, or unpublished pages appear
        $forbiddenSlugs = [];
        foreach ($pageSpecs as $spec) {
            if (
                $spec['status'] !== 'published'
                || $spec['is_enabled'] !== true
                || $spec['published_at'] === null
            ) {
                $forbiddenSlugs[] = $spec['slug'];
            }
        }

        foreach ($entries as $entry) {
            foreach ($forbiddenSlugs as $slug) {
                $this->assertStringNotContainsString(
                    $slug,
                    $entry->loc,
                    "Sitemap must not contain entry for non-published/disabled page with slug: {$slug}"
                );
            }
        }
    }

    public function test_sitemap_excludes_pages_scheduled_for_future_publication(): void
    {
        $page = Page::create([
            'slug' => 'future-publication-page',
            'type' => 'landing',
            'template' => 'default',
            'status' => 'published',
            'is_enabled' => true,
            'is_homepage_shell' => false,
            'published_at' => now()->subDay(),
            'publish_at' => now()->addDay(),
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Future Publication Page',
        ]);

        $entries = app(SitemapServiceInterface::class)->generateEntries();

        $this->assertFalse(
            $entries->contains(static fn ($entry): bool => str_contains($entry->loc, 'future-publication-page')),
        );
    }

    public function test_sitemap_excludes_pages_with_unrenderable_ancestors(): void
    {
        $parent = Page::create([
            'slug' => 'disabled-parent-page',
            'type' => 'landing',
            'template' => 'default',
            'status' => 'published',
            'is_enabled' => false,
            'is_homepage_shell' => false,
            'published_at' => now()->subDay(),
        ]);

        $child = Page::create([
            'parent_id' => $parent->id,
            'slug' => 'child-under-disabled-parent',
            'type' => 'landing',
            'template' => 'default',
            'status' => 'published',
            'is_enabled' => true,
            'is_homepage_shell' => false,
            'published_at' => now()->subDay(),
        ]);

        PageTranslation::create([
            'page_id' => $child->id,
            'locale' => 'en',
            'title' => 'Child Under Disabled Parent',
        ]);

        $entries = app(SitemapServiceInterface::class)->generateEntries();

        $this->assertFalse(
            $entries->contains(static fn ($entry): bool => str_contains($entry->loc, 'child-under-disabled-parent')),
        );
    }
}
