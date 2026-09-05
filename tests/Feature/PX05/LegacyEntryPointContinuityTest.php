<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Legacy\LegacyExactRedirect;
use App\Models\Legacy\LegacyPatternRule;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LegacyEntryPointRedirectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Covers the legacy entry points that the old homepage links but that had no
 * rule on the new site: the bare subsite directory roots, the root
 * "service=N" content lists, and the per-faculty honour rolls.
 *
 * Every mapping here is asserted the way the maintenance guide requires — the
 * legacy URL must redirect once, and the destination it names must itself
 * answer 200. A redirect that lands on a 404 is the failure mode this file
 * exists to prevent.
 */
final class LegacyEntryPointContinuityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bare old subsite roots => destination. Probed on the live old site on
     * 2026-08-29: each one 301s to its trailing-slash form and serves 200.
     *
     * @var array<string, string>
     */
    private const BARE_ROOTS = [
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
     * Old root content-list URLs => destination, per service id.
     *
     * @var array<string, string>
     */
    private const SERVICE_LISTS = [
        '/index.php?page=list&ex=2&dir=items&lang=1&service=3' => '/ar/news',
        '/index.php?page=list&ex=2&dir=items&lang=2&service=3' => '/en/news',
        '/index.php?page=list&ex=2&dir=items&lang=1&service=4' => '/ar/news/announcements',
        '/index.php?page=list&ex=2&dir=items&lang=2&service=4' => '/en/news/announcements',
        '/index.php?page=list&ex=2&dir=items&lang=1&service=5' => '/ar/about/partnerships',
        '/index.php?page=list&ex=2&dir=items&lang=2&service=5' => '/en/about/partnerships',
        '/index.php?page=list&ex=2&dir=items&lang=1&service=6' => '/ar/news',
        '/index.php?page=list&ex=2&dir=items&lang=2&service=6' => '/en/news',
        '/index.php?page=list&ex=2&dir=items&lang=1&service=7' => '/ar/news',
        '/index.php?page=list&ex=2&dir=items&lang=2&service=7' => '/en/news',
        '/index.php?page=list&ex=2&dir=items&lang=1&service=10' => '/ar/news/events-list',
        '/index.php?page=list&ex=2&dir=items&lang=2&service=10' => '/en/news/events-list',
    ];

    /**
     * Old per-faculty honour roll => the new valedictorians subpage.
     *
     * @var array<string, string>
     */
    private const HONOUR_ROLLS = [
        '/med/index.php?page=list&ex=2&dir=good_students&lang=1' => '/ar/faculties/medicine/valedictorians',
        '/dent/index.php?page=list&ex=2&dir=good_students&lang=1' => '/ar/faculties/dentistry/valedictorians',
        '/pharm/index.php?page=list&ex=2&dir=good_students&lang=1' => '/ar/faculties/pharmacy/valedictorians',
        '/info/index.php?page=list&ex=2&dir=good_students&lang=1' => '/ar/faculties/artificial-intelligence/valedictorians',
        '/petrol/index.php?page=list&ex=2&dir=good_students&lang=1' => '/ar/faculties/petroleum/valedictorians',
        '/admin/index.php?page=list&ex=2&dir=good_students&lang=1' => '/ar/faculties/business-administration/valedictorians',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(LegacyEntryPointRedirectSeeder::class);
        $this->publishCampusLifeSections();
    }

    public function test_bare_subsite_directory_roots_redirect_once_to_a_page_that_returns_200(): void
    {
        foreach (self::BARE_ROOTS as $legacyPath => $destination) {
            $this->assertRedirectsOnceTo($legacyPath, $destination, 301);
        }
    }

    public function test_bare_subsite_roots_resolve_identically_with_a_trailing_slash(): void
    {
        // Apache answered /med with a 301 to /med/, so both spellings are in the
        // wild. Laravel normalises the trailing slash away, so one rule covers
        // both — this asserts that normalisation actually holds.
        foreach (self::BARE_ROOTS as $legacyPath => $destination) {
            $this->get($legacyPath.'/')
                ->assertStatus(301)
                ->assertRedirect($destination);
        }
    }

    public function test_root_service_list_urls_redirect_once_to_a_section_that_returns_200(): void
    {
        foreach (self::SERVICE_LISTS as $legacyUrl => $destination) {
            $this->assertRedirectsOnceTo($legacyUrl, $destination, 301);
        }
    }

    public function test_faculty_honour_rolls_redirect_once_to_the_valedictorians_subpage(): void
    {
        foreach (self::HONOUR_ROLLS as $legacyUrl => $destination) {
            $this->assertRedirectsOnceTo($legacyUrl, $destination, 301);
        }
    }

    public function test_root_honour_roll_page_stays_an_honest_404(): void
    {
        // The old root honour page renders its heading and nothing else — diffed
        // against the old site's empty-page template on 2026-08-29 it carried 11
        // characters of body, versus 113 for the contact page. There is no
        // university-wide honour list on the new site to send it to, so it stays
        // a 404 and gets logged for triage rather than guessing a destination.
        $this->get('/index.php?lang=1&dir=html&ex=1&page=good_students')->assertNotFound();
        $this->get('/index.php?lang=2&dir=html&ex=1&page=good_students')->assertNotFound();
    }

    public function test_private_members_archive_honour_roll_stays_404(): void
    {
        // Invariant 1.5: the members archive must never resolve to a record, and
        // adding good_students to the subsite resolver must not open a way in.
        $this->get('/members/index.php?page=list&ex=2&dir=good_students&lang=1')->assertNotFound();
    }

    public function test_unmapped_service_list_ids_stay_404(): void
    {
        // Only the six service ids the old homepage links were reviewed. An
        // unreviewed id must not be absorbed by a pattern.
        $this->get('/index.php?page=list&ex=2&dir=items&lang=1&service=99')->assertNotFound();
        $this->get('/index.php?page=list&ex=2&dir=items&lang=1&service=8')->assertNotFound();
    }

    public function test_subsite_service_decade_mismatch_still_does_not_resolve(): void
    {
        // Invariant 1.3: service 3 is a root service, so a faculty subsite must
        // not accept it just because the units digit names a valid kind.
        $this->get('/med/index.php?dir=items&page=list&lang=1&service=3')->assertNotFound();
    }

    public function test_alumni_root_negotiates_locale_and_lands_on_200(): void
    {
        // /alumni is both an old subsite root and a live new-site path, so it is
        // answered by the unprefixed reference route rather than an exact rule —
        // that keeps the visitor's own locale instead of forcing Arabic.
        $arabic = $this->get('/alumni', ['Accept-Language' => 'ar']);
        $arabic->assertStatus(302)->assertRedirect('/ar/alumni');
        $this->get('/ar/alumni')->assertOk();

        $english = $this->get('/alumni', ['Accept-Language' => 'en']);
        $english->assertStatus(302)->assertRedirect('/en/alumni');
        $this->get('/en/alumni')->assertOk();
    }

    public function test_existing_entry_point_redirects_are_unchanged(): void
    {
        $this->get('/index.php?lang=1')->assertStatus(301)->assertRedirect('/ar');
        $this->get('/index.php?lang=1&dir=html&ex=1&page=contactus')
            ->assertStatus(301)
            ->assertRedirect('/ar/contact');
        $this->get('/med/index.php')->assertStatus(301)->assertRedirect('/ar/faculties/medicine');
        $this->get('/admin/index.php')
            ->assertStatus(301)
            ->assertRedirect('/ar/faculties/business-administration');
    }

    public function test_bare_admin_root_is_left_to_the_cms_and_never_redirected_to_the_faculty(): void
    {
        // Deliberate decision: every indexed Business Administration URL uses the
        // /admin/index.php spelling, which is mapped above. The bare root keeps
        // the CMS panel, so the middleware must not claim it.
        $response = $this->get('/admin');

        $this->assertNotSame(
            '/ar/faculties/business-administration',
            $response->headers->get('Location'),
            'Bare /admin must stay with the CMS panel, not redirect to the public faculty page.',
        );
    }

    public function test_exact_rules_still_win_over_pattern_rules(): void
    {
        LegacyPatternRule::create([
            'pattern' => '#^/med$#',
            'replacement' => '/ar/news',
            'status_code' => 301,
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->get('/med')->assertStatus(301)->assertRedirect('/ar/faculties/medicine');
    }

    public function test_new_entry_points_add_no_duplicate_or_conflicting_rules(): void
    {
        $this->artisan('continuity:validate-redirects')->assertSuccessful();

        $paths = LegacyExactRedirect::query()->pluck('legacy_path')->all();

        $this->assertSame(
            count($paths),
            count(array_unique($paths)),
            'Seeding the entry points twice must not create duplicate legacy paths.',
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $before = LegacyExactRedirect::query()->count();
        $this->seed(LegacyEntryPointRedirectSeeder::class);

        $this->assertSame($before, LegacyExactRedirect::query()->count());
        $this->get('/med')->assertStatus(301)->assertRedirect('/ar/faculties/medicine');
    }

    /**
     * Assert the legacy URL redirects with the expected status to the expected
     * destination, and that the destination itself answers 200 in one hop.
     */
    private function assertRedirectsOnceTo(string $legacyUrl, string $destination, int $status): void
    {
        $response = $this->get($legacyUrl);

        $response->assertStatus($status);
        $response->assertRedirect($destination);

        $this->followedDestination($destination)->assertOk();
    }

    private function followedDestination(string $destination): TestResponse
    {
        $response = $this->get($destination);

        $this->assertFalse(
            $response->isRedirect(),
            sprintf('Destination %s must answer directly, not redirect again.', $destination),
        );

        return $response;
    }

    /**
     * The three campus-life sections are editorial CMS content rather than
     * seeded structure, so publish a minimal payload for them. They are live on
     * the deployed site (probed 200 on v2.spu.edu.sy, 2026-08-29); without this
     * the destinations would 404 only because the test fixture is empty.
     */
    private function publishCampusLifeSections(): void
    {
        foreach (['hospital', 'dental', 'clubs-activities'] as $slug) {
            CmsTargetContent::query()->updateOrCreate(
                ['target_key' => 'campus_life.'.$slug],
                [
                    'status' => PublicationStatus::Published->value,
                    'payload_json' => [
                        'translations' => [
                            'ar' => ['title' => 'قسم '.$slug, 'body' => 'محتوى'],
                            'en' => ['title' => 'Section '.$slug, 'body' => 'Content'],
                        ],
                    ],
                ],
            );
        }
    }
}
