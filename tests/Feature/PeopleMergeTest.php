<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Content\PeopleMergeServiceInterface;
use App\Enums\PublicationStatus;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Research\Researcher;
use App\Models\Research\ResearchPublication;
use App\Services\Content\PeopleMergeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class PeopleMergeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_people_entered_twice_are_merged_and_staff_in_two_faculties_are_left_alone(): void
    {
        $dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        $pharmacy = Faculty::query()->where('public_slug', 'pharmacy')->firstOrFail();
        $petroleum = Faculty::query()->where('public_slug', 'petroleum')->firstOrFail();

        $kept = $this->member('d-ramz-slyman-sqyrq', $dentistry, 'Dr. Ramez Slaiman Skerek', 'د. رامز سليمان سقيرق', bio: 'Orthodontist.');
        $duplicate = $this->member('d-ramz-sqyrq', $dentistry, 'Dr. Ramez Skerek', 'د. رامز سقيرق', email: 'ramez@spu.edu.sy');
        $this->member('a-m-mhmwd-alghfry', $pharmacy, 'Asst. Prof. Mahmoud Al-Ghafri', 'أ. م. محمود الغفري');
        $this->member('d-mhmwd-alghfry', $petroleum, 'Dr. Mahmoud Al-Ghafri', 'د. محمود الغفري');
        $publication = ResearchPublication::query()->create(['faculty_member_id' => $duplicate->getKey(), 'is_enabled' => false]);

        $paper = ResearchPublication::query()->create(['is_enabled' => false]);
        $other = ResearchPublication::query()->create(['is_enabled' => false]);
        $saeed = Researcher::query()->create(['full_name' => 'Saeed A Kadri']);
        $saeedAgain = Researcher::query()->create(['full_name' => 'Saeed Kadri']);
        $saeed->publications()->attach([$paper->getKey() => ['role' => 'co_author', 'sort_order' => 1], $other->getKey() => ['role' => 'co_author', 'sort_order' => 1]]);
        $saeedAgain->publications()->attach($paper->getKey(), ['role' => 'lead_author', 'sort_order' => 1]);
        $saeedOnly = ResearchPublication::query()->create(['is_enabled' => false]);
        $saeedAgain->publications()->attach($saeedOnly->getKey(), ['role' => 'co_author', 'sort_order' => 2]);

        $service = app(PeopleMergeServiceInterface::class);
        $dryRun = $service->merge(false, null);
        $this->assertSame([1, 1, 1], [$dryRun->researchersMerged, $dryRun->membersMerged, $dryRun->leftAlone]);
        $this->assertNotSoftDeleted($duplicate);

        try {
            $service->merge(true, 'wrong');
            $this->fail('The merge ran without its approval token.');
        } catch (InvalidArgumentException) {
        }

        $service->merge(true, PeopleMergeService::APPROVAL_TOKEN);

        $this->assertSoftDeleted($duplicate);
        $kept->refresh();
        $this->assertSame('ramez@spu.edu.sy', $kept->email);
        $this->assertSame((int) $kept->getKey(), (int) $publication->refresh()->faculty_member_id);
        $this->assertSame(2, FacultyMember::query()->where('slug', 'like', '%alghfry')->count());
        $this->assertSame('/ar/about/profile/d-ramz-slyman-sqyrq', DB::table('legacy_exact_redirects')->where('legacy_path', '/ar/about/profile/d-ramz-sqyrq')->value('destination_url'));
        $this->get('/en/about/profile/d-ramz-sqyrq')->assertRedirect('/en/about/profile/d-ramz-slyman-sqyrq');

        $this->assertSoftDeleted($saeedAgain);
        $this->assertSame((int) $saeed->getKey(), (int) $saeedAgain->refresh()->merged_into_id);
        $this->assertEqualsCanonicalizing([$paper->getKey(), $other->getKey(), $saeedOnly->getKey()], $saeed->publications()->pluck('research_publications.id')->all());
    }

    public function test_a_faculty_member_with_a_leadership_profile_is_listed_once_as_that_profile(): void
    {
        $pharmacy = Faculty::query()->where('public_slug', 'pharmacy')->firstOrFail();
        $member = $this->member('d-hsam-aldyn-shhrwr', $pharmacy, 'Dr. Hossam El-Din Shahrour', 'د. حسام الدين شحرور', bio: 'Pharmacology.');
        $person = Person::query()->create(['slug' => 'hossam-shahrour-test', 'category' => 'dean', 'is_enabled' => true, 'publication_status' => PublicationStatus::Published->value, 'published_at' => now()->subDay(), 'faculty_scope_slug' => 'pharmacy']);
        $person->translations()->create(['locale' => 'en', 'name' => 'Dr. Hossam Shahrour', 'role' => 'Dean']);
        $person->translations()->create(['locale' => 'ar', 'name' => 'د. حسام شحرور', 'role' => 'العميد']);
        Person::query()->whereKeyNot($person->getKey())->whereHas('translations', fn ($q) => $q->where('name', 'like', '%شحرور%'))->delete();

        $result = app(PeopleMergeServiceInterface::class)->merge(true, PeopleMergeService::APPROVAL_TOKEN);

        $this->assertGreaterThanOrEqual(1, $result->membersLinked);
        $this->assertSame((int) $person->getKey(), (int) $member->refresh()->person_id);
        $this->assertTrue($person->appointments()->where('type', 'faculty_member')->where('faculty_id', $pharmacy->getKey())->exists());
        $this->assertSame('Pharmacology.', $person->translations()->where('locale', 'en')->value('bio'));

        Cache::flush();
        $page = $this->get('/en/faculties/pharmacy/members')->assertOk()->getContent();
        $this->assertStringContainsString('/en/about/profile/hossam-shahrour-test', (string) $page);
        $this->assertStringNotContainsString('/en/about/profile/d-hsam-aldyn-shhrwr', (string) $page);
    }

    private function member(string $slug, Faculty $faculty, string $en, string $ar, ?string $bio = null, ?string $email = null): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => $faculty->getKey(),
            'email' => $email,
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $en, 'bio' => $bio]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $ar]);

        return $member;
    }
}
