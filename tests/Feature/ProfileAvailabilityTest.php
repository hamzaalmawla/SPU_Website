<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Page\ProfilePageServiceInterface;
use App\Enums\PublicationStatus;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Navigation and the sitemap ask "is this profile safe to link?" before
 * rendering a link to it. The cheap availability check that answers has to agree
 * with what actually happens when a visitor follows the link.
 *
 * It once did not: it tested for a public row, while the page itself 404s unless
 * a usable translation exists. A published person with no Arabic and no English
 * translation was therefore linked from the menu, published in the sitemap, and
 * dead on arrival.
 */
final class ProfileAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_person_without_any_translation_is_not_available(): void
    {
        $this->publishedPerson('ghost-profile');

        // Without this the test could pass for the wrong reason — a row that is
        // simply not public would prove nothing about the translation gap.
        $this->assertTrue(
            Person::query()->public()->where('slug', 'ghost-profile')->exists(),
            'The fixture must be a genuinely public row, or this asserts nothing.',
        );

        $this->assertFalse(
            app(ProfilePageServiceInterface::class)->hasPublicProfile('ghost-profile'),
            'A profile whose page would 404 must never be reported as available.',
        );
    }

    public function test_availability_agrees_with_what_the_page_actually_does(): void
    {
        $this->publishedPerson('ghost-profile');

        $service = app(ProfilePageServiceInterface::class);

        // The invariant, stated directly: the cheap check and the real render
        // must never disagree, in either direction.
        $this->assertSame(
            $service->getProfile('ar', 'person', 'ghost-profile') !== null,
            $service->hasPublicProfile('ghost-profile'),
        );

        $this->get('/ar/research/researchers/ghost-profile')->assertNotFound();
    }

    public function test_a_translated_person_is_available(): void
    {
        $person = $this->publishedPerson('real-profile');
        $person->translations()->create([
            'locale' => 'ar',
            'name' => 'اسم الباحث',
            'role' => 'أستاذ',
        ]);

        $service = app(ProfilePageServiceInterface::class);

        $this->assertTrue($service->hasPublicProfile('real-profile'));
        $this->assertTrue($service->hasAnyPublicProfile());
    }

    public function test_untranslated_people_alone_do_not_make_the_directory_available(): void
    {
        $this->publishedPerson('ghost-one');
        $this->publishedPerson('ghost-two');

        $this->assertFalse(
            app(ProfilePageServiceInterface::class)->hasAnyPublicProfile(),
            'A directory of profiles that all 404 is not a directory worth linking.',
        );
    }

    public function test_a_translationless_person_does_not_shadow_a_renderable_faculty_member(): void
    {
        // Both tables can hold the same slug — getPublicProfiles() calls
        // unique() across the merged set, so this is an expected state.
        $this->publishedPerson('shared-slug');
        $this->publishedFacultyMember('shared-slug');

        $service = app(ProfilePageServiceInterface::class);

        // Person is still preferred, but only when it renders. Resolving to the
        // FacultyMember here is what keeps availability and the real page in
        // agreement; returning null would make the nav link dead.
        $this->assertNotNull(
            $service->getProfile('ar', 'unified', 'shared-slug'),
            'A renderable FacultyMember must not be shadowed by a Person that cannot render.',
        );

        $this->assertTrue($service->hasPublicProfile('shared-slug'));
    }

    public function test_navigation_drops_a_seeded_profile_link_when_the_person_is_unpublished(): void
    {
        $this->seed(DatabaseSeeder::class);

        // The seeded "featured researcher" menu entries point at the canonical
        // /about/profile/{slug} path. That path sits outside the research
        // availability check that used to cover them, so without an explicit
        // guard an unpublished person keeps a menu link to a 404.
        $this->get('/ar')->assertOk()->assertSee('/ar/about/profile/mouhib-alnoukari', false);

        Person::query()->where('slug', 'mouhib-alnoukari')->update(['is_enabled' => false]);
        Cache::flush();

        $this->get('/ar')->assertOk()->assertDontSee('/ar/about/profile/mouhib-alnoukari', false);
    }

    public function test_a_member_whose_canonical_person_is_unpublished_is_not_available(): void
    {
        // hamza's person_id indirection: a member carrying one does not render
        // itself, resolveUnifiedProfile() resolves it through the Person. So the
        // member's own state says nothing about whether the page exists.
        $person = $this->publishedPerson('linked-person');
        $person->translations()->create(['locale' => 'ar', 'name' => 'اسم', 'role' => 'دور']);

        $member = $this->publishedFacultyMember('linked-member');
        $member->forceFill(['person_id' => $person->getKey()])->save();

        $service = app(ProfilePageServiceInterface::class);
        $this->assertTrue($service->hasPublicProfile('linked-member'));

        $person->forceFill(['is_enabled' => false])->save();

        $this->assertFalse(
            $service->hasPublicProfile('linked-member'),
            'A member whose canonical Person is unpublished renders a 404, so it is not available.',
        );
        $this->assertNull($service->getProfile('ar', 'unified', 'linked-member'));
    }

    public function test_a_member_whose_canonical_person_has_no_translation_is_not_available(): void
    {
        $person = $this->publishedPerson('bare-person');   // no translations
        $member = $this->publishedFacultyMember('bare-member');
        $member->forceFill(['person_id' => $person->getKey()])->save();

        $service = app(ProfilePageServiceInterface::class);

        $this->assertNull($service->getProfile('ar', 'unified', 'bare-member'));
        $this->assertFalse(
            $service->hasPublicProfile('bare-member'),
            'The check and the render must agree even through the person_id indirection.',
        );
    }

    private function publishedFacultyMember(string $slug): FacultyMember
    {
        $faculty = Faculty::query()->firstOr(static fn (): Faculty => Faculty::query()->create([
            'slug' => 'medicine',
            'sort_order' => 1,
            'is_enabled' => true,
        ]));

        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => (int) $faculty->getKey(),
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);

        $member->translations()->create([
            'locale' => 'ar',
            'full_name' => 'عضو هيئة تدريسية',
            'position' => 'أستاذ',
        ]);

        return $member;
    }

    private function publishedPerson(string $slug): Person
    {
        return Person::query()->create([
            'slug' => $slug,
            'category' => 'academic',
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
    }
}
