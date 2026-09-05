<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the deterministic half of old-site URL continuity: the legacy entry
 * points whose replacement is a documented fact rather than an editorial call.
 *
 * Scope rules (see Docs/OLD_TO_NEW_REDIRECT_MIGRATION_COMPLETE.md):
 *
 *  - Only URLs whose new equivalent is proven are listed here. Old modules with
 *    no public equivalent on the new site (/downloads/, /members/,
 *    dent_conf_search.php) are deliberately absent so they keep returning a real
 *    404 and get logged into unresolved_legacy_requests for triage. Alumni list
 *    queries are handled separately by the reviewed query resolver; unknown and
 *    per-record alumni URLs remain 404.
 *  - Nothing here points an unknown URL at the homepage.
 *  - Retired languages (old language ids 3/6/7 = German, Spanish, French) use the
 *    approved 302 fallback to /en, matching old_database.unsupported_language_continuity.
 *  - Every row carries a decision_batch so the whole set can be rolled back with
 *    legacy-import:redirect-rollback.
 *
 * These rows are stored WITHOUT a query_signature. For legacy router paths
 * (index.php) ContinuityService only matches a null signature when the incoming
 * request has no query string, so these rules resolve the bare subsite home URL
 * and can never hijack a deep content URL such as
 * /med/index.php?dir=items&page=show&cat_id=123 — those stay with the typed
 * query resolvers, or stay unresolved.
 */
class LegacyEntryPointRedirectSeeder extends Seeder
{
    private const BATCH = 'deterministic-entry-points-v1';

    /**
     * Old subsite router home => canonical new landing page.
     *
     * The old site served Arabic for a URL that carried no lang parameter, so a
     * bare router URL redirects straight to the Arabic page in one hop rather
     * than bouncing through locale negotiation.
     *
     * @var array<string, string>
     */
    private const SUBSITE_HOMES = [
        // The old site's own homepage.
        '/index.php' => '/ar',
        '/med/index.php' => '/ar/faculties/medicine',
        '/dent/index.php' => '/ar/faculties/dentistry',
        '/pharm/index.php' => '/ar/faculties/pharmacy',
        '/info/index.php' => '/ar/faculties/artificial-intelligence',
        '/petrol/index.php' => '/ar/faculties/petroleum',
        // The old public Business-faculty URL. It is NOT the new Laravel admin
        // panel; RedirectContinuityMiddleware whitelists this exact path so the
        // two never collide.
        '/admin/index.php' => '/ar/faculties/business-administration',
        '/research/index.php' => '/ar/research',
        '/hospital/index.php' => '/ar/campus-life/hospital',
        '/dent_clinic/index.php' => '/ar/campus-life/dental',
        '/clubs/index.php' => '/ar/campus-life/clubs-activities',
    ];

    /**
     * Bare old subsite directory roots => canonical new landing page.
     *
     * The live old site links these from its own homepage as relative paths
     * ("med", "dent", …) and Apache mod_dir answers each one with a 301 to the
     * trailing-slash form, which then serves that subsite's index.php:
     *
     *     GET /med   -> 301 /med/   -> 200 (Faculty of Medicine home)
     *
     * Probed against https://spu.edu.sy on 2026-08-29: all eleven bare roots
     * behave that way, and all eleven trailing-slash forms return 200.
     *
     * SUBSITE_HOMES above only covers the "/med/index.php" spelling, so the bare
     * form had no rule and returned a real 404 on v2. Laravel normalises the
     * trailing slash away — Request::path() reports "med" for both "/med" and
     * "/med/" — so one row per subsite covers both spellings.
     *
     * Three legacy roots are deliberately NOT listed here:
     *
     *  - "/research" and "/alumni" are also live paths on the new site, so they
     *    are answered by the unprefixed reference route in routes/web.php, which
     *    negotiates the visitor's locale instead of forcing Arabic. An exact rule
     *    here would run in the middleware, before routing, and would throw that
     *    negotiation away.
     *  - "/admin" is the CMS panel on the new site. See the note on
     *    SUBSITE_HOMES['/admin/index.php'] and section 13 of
     *    Docs/LEGACY_REDIRECT_MAINTENANCE_GUIDE.md: every indexed Business
     *    Administration URL uses the "/admin/index.php" spelling, which is
     *    already mapped, so the bare root is left with the panel on purpose.
     *
     * @var array<string, string>
     */
    private const SUBSITE_DIRECTORY_ROOTS = [
        '/med' => '/ar/faculties/medicine',
        '/dent' => '/ar/faculties/dentistry',
        '/pharm' => '/ar/faculties/pharmacy',
        '/info' => '/ar/faculties/artificial-intelligence',
        '/petrol' => '/ar/faculties/petroleum',
        '/hospital' => '/ar/campus-life/hospital',
        '/dent_clinic' => '/ar/campus-life/dental',
        '/clubs' => '/ar/campus-life/clubs-activities',
    ];

    /**
     * Old root entry points => new equivalent, with the status code to use.
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const ROOT_ENTRY_POINTS = [
        // Events listing pages (verified as real HTML pages on the old site).
        '/ar_events.php' => ['/ar/news/events-list', 301],
        '/en_events.php' => ['/en/news/events-list', 301],
        '/ar_cal_events.php' => ['/ar/news/events', 301],
        '/en_cal_events.php' => ['/en/news/events', 301],

        // Retired languages: approved 302 fallback, never a 301.
        '/ge_events.php' => ['/en', 302],
        '/sp_events.php' => ['/en', 302],
        '/fr_events.php' => ['/en', 302],
        '/ge_cal_events.php' => ['/en', 302],
        '/sp_cal_events.php' => ['/en', 302],
        '/fr_cal_events.php' => ['/en', 302],

        // The old sitemap was generated by PHP and is served as XML.
        '/sitemap.php' => ['/sitemap.xml', 301],
        '/sitemap1.php' => ['/sitemap.xml', 301],
        '/sitemap2.php' => ['/sitemap.xml', 301],

        // Homepage slider fragment: a visitor landing here wants the homepage.
        '/slider.php' => ['/ar', 301],
    ];

    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (self::ROOT_ENTRY_POINTS as $legacyPath => [$destination, $status]) {
            $rows[] = $this->row($legacyPath, $destination, $status, $now, 'Old root entry point.');
        }

        foreach (self::SUBSITE_HOMES as $legacyPath => $destination) {
            $rows[] = $this->row($legacyPath, $destination, 301, $now, 'Old subsite router home (bare URL, no query).');
        }

        foreach (self::SUBSITE_DIRECTORY_ROOTS as $legacyPath => $destination) {
            $rows[] = $this->row($legacyPath, $destination, 301, $now, 'Old subsite directory root, linked from the old homepage; covers the trailing-slash form too.');
        }

        foreach ($rows as $row) {
            DB::table('legacy_exact_redirects')->updateOrInsert(
                ['legacy_path' => $row['legacy_path'], 'query_signature' => null],
                $row,
            );
        }

        $this->command?->info(sprintf('Seeded %d deterministic legacy entry-point redirects.', count($rows)));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $legacyPath, string $destination, int $status, \DateTimeInterface $now, string $note): array
    {
        return [
            'legacy_path' => $legacyPath,
            'query_signature' => null,
            'destination_url' => $destination,
            'status_code' => $status,
            'locale' => str_starts_with($destination, '/en') ? 'en' : 'ar',
            'is_active' => true,
            'notes' => $note.' Deterministic mapping, no editorial judgement required.',
            'decision_batch' => self::BATCH,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
