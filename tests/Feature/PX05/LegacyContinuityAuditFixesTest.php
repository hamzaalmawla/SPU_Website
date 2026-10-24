<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Contracts\Legacy\LegacyUrlNormalizerInterface;
use App\Services\Legacy\LegacyQueryResolverRegistry;
use Database\Seeders\LegacyEntryPointRedirectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Findings from the live continuity audit of 2026-09-01, each pinned so it
 * cannot come back.
 */
final class LegacyContinuityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The old site's FAQ page is its most-linked remaining gap — 87 inbound
     * links, because it sits in the footer of every page — and the destination
     * was already serving. Only the rule was missing.
     */
    public function test_the_old_faq_url_resolves_in_both_languages(): void
    {
        $normalizer = app(LegacyUrlNormalizerInterface::class);
        $registry = app(LegacyQueryResolverRegistry::class);

        foreach (['1' => 'ar', '2' => 'en'] as $langId => $locale) {
            $normalized = $normalizer->normalize('/index.php', "lang={$langId}&page=faqs&ex=2&dir=faqs");
            $resolution = $registry->resolve($normalized);

            $this->assertNotNull($resolution, "The lang={$langId} FAQ URL must resolve.");
            $this->assertSame("/{$locale}/admissions/faq", $resolution->targetUrl);
        }
    }

    /**
     * An unrecognised directory in front of a legacy entrypoint used to be
     * treated as the root site, so it redirected to the homepage with a 200 —
     * a soft 404. Search engines read that as a real page, and it never reaches
     * unresolved_legacy_requests, so the gap stays invisible.
     */
    public function test_an_unknown_subsite_is_not_treated_as_the_root_site(): void
    {
        $normalizer = app(LegacyUrlNormalizerInterface::class);

        foreach (['/bogussubsite/index.php', '/dent_clini/index.php'] as $path) {
            $this->assertSame(
                'unknown',
                $normalizer->normalize($path, 'lang=1')->subsite->key,
                $path.' must not be classified as the root site.',
            );
        }
    }

    /** The narrowing must not reclassify the genuine root or static paths. */
    public function test_the_real_root_and_static_paths_are_untouched(): void
    {
        $normalizer = app(LegacyUrlNormalizerInterface::class);

        $this->assertSame('root', $normalizer->normalize('/index.php', 'lang=1')->subsite->key);
        $this->assertSame('root', $normalizer->normalize('/downloads/files/report.pdf', null)->subsite->key);
        $this->assertSame('med', $normalizer->normalize('/med/index.php', 'lang=1')->subsite->key);
    }

    /**
     * The subsite-root rules shipped as code without their rows, so eight
     * redirects went live pointing at a 404. The deploy now runs this seeder;
     * this asserts the rows it is expected to produce.
     */
    public function test_the_seeder_creates_the_subsite_directory_roots(): void
    {
        $this->seed(LegacyEntryPointRedirectSeeder::class);

        foreach (['/med', '/dent', '/pharm', '/info', '/petrol', '/hospital', '/dent_clinic', '/clubs'] as $path) {
            $this->assertDatabaseHas('legacy_exact_redirects', [
                'legacy_path' => $path,
                'query_signature' => null,
            ]);
        }
    }

    /** Running it twice must not duplicate or fail — the deploy repeats it. */
    public function test_the_seeder_is_idempotent(): void
    {
        $this->seed(LegacyEntryPointRedirectSeeder::class);
        $first = \DB::table('legacy_exact_redirects')->count();

        $this->seed(LegacyEntryPointRedirectSeeder::class);

        $this->assertSame($first, \DB::table('legacy_exact_redirects')->count());
    }
}
