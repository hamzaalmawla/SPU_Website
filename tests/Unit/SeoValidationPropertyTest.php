<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Page;
use App\Models\PageSeoMeta;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\PropertyTestHelpers;
use Tests\TestCase;

/**
 * Property-based tests for SEO completeness validation.
 *
 * Feature: spu-homepage-admin-foundation
 */
#[Group('property')]
class SeoValidationPropertyTest extends TestCase
{
    use PropertyTestHelpers;
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────
    // Property 12: SEO completeness validation identifies weak entries
    // Feature: spu-homepage-admin-foundation, Property 12: SEO completeness validation identifies weak entries
    // **Validates: Requirements 27.5**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: list<string>}>
     */
    public static function seoCompletenessProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 110; $i++) {
            $locale = self::randomLocale();

            // Randomly null/empty out SEO fields to create weak entries
            $hasTitle = random_int(0, 2) > 0;
            $hasDescription = random_int(0, 2) > 0;
            $hasCanonical = random_int(0, 2) > 0;

            $metaTitle = $hasTitle ? self::randomSentence() : (random_int(0, 1) === 1 ? null : '');
            $metaDescription = $hasDescription ? self::randomSentence() : (random_int(0, 1) === 1 ? null : '');
            $canonicalUrl = $hasCanonical ? 'https://spu.edu.sy/' . $locale . '/' . self::randomSlugPath() : (random_int(0, 1) === 1 ? null : '');

            // Determine which issues should be flagged
            $expectedIssues = [];
            if (! $hasTitle) {
                $expectedIssues[] = 'missing_meta_title';
            }
            if (! $hasDescription) {
                $expectedIssues[] = 'missing_meta_description';
            }
            // Note: missing_canonical_url is only flagged when BOTH the SEO record
            // and the service fallback have no canonical URL. The service always
            // generates a fallback canonical, so we only expect this flag when
            // the record has no canonical AND we explicitly test that edge case.
            // For this property test we focus on meta_title and meta_description
            // which have no automatic fallback in the SEO record check.

            $cases["iteration_{$i}"] = [$locale, $metaTitle, $metaDescription, $canonicalUrl, $expectedIssues];
        }

        return $cases;
    }

    /**
     * @param  list<string>  $expectedIssues
     */
    #[DataProvider('seoCompletenessProvider')]
    public function test_seo_validation_flags_pages_with_weak_metadata(
        string $locale,
        ?string $metaTitle,
        ?string $metaDescription,
        ?string $canonicalUrl,
        array $expectedIssues,
    ): void {
        // Create a published, enabled page
        $page = Page::create([
            'slug' => 'test-seo-' . self::randomSlugSegment(),
            'type' => 'landing',
            'template' => 'default',
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now()->subDay(),
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => $locale,
            'title' => 'Test Page',
        ]);

        PageSeoMeta::create([
            'page_id' => $page->id,
            'locale' => $locale,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'canonical_url' => $canonicalUrl,
            'og_title' => $metaTitle, // mirror to avoid unrelated og flags
            'og_description' => $metaDescription,
            'og_image_url' => 'https://spu.edu.sy/images/placeholder.jpg',
        ]);

        // Run the SEO validation command and capture JSON output
        Artisan::call('continuity:validate-seo', [
            '--locale' => $locale,
            '--format' => 'json',
        ]);

        $output = Artisan::output();

        // Extract JSON from the command output (command also outputs info lines)
        preg_match('/\{[\s\S]*\}/', $output, $matches);
        $this->assertNotEmpty($matches, 'Command output must contain JSON payload');

        $payload = json_decode($matches[0], true);
        $this->assertIsArray($payload, 'Command must output valid JSON');

        if ($expectedIssues === []) {
            // No core SEO issues — page should not be flagged for these fields
            $this->assertSame(0, $payload['pages_with_issues'], 'Page with complete SEO should not be flagged');
        } else {
            // Page should be flagged
            $this->assertGreaterThan(0, $payload['pages_with_issues'], 'Page with weak SEO must be flagged');
            $this->assertNotEmpty($payload['items'], 'Items must contain the flagged page');

            $pageIssues = $payload['items'][0]['issues'] ?? [];

            foreach ($expectedIssues as $expectedIssue) {
                $this->assertContains(
                    $expectedIssue,
                    $pageIssues,
                    "Issue '{$expectedIssue}' must be reported for page with weak SEO"
                );
            }
        }
    }
}
