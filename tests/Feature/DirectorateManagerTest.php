<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Enums\PublicationStatus;
use App\Models\Content\Directorate;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DirectorateManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_published_manager_shows_name_contact_and_education_from_the_profile(): void
    {
        $manager = $this->managerProfile(published: true);
        $this->publishDirectorate(function (array $payload) use ($manager): array {
            $payload['manager_member_id'] = (int) $manager->getKey();

            return $payload;
        });

        $this->get('/en/about/directorates/it-services')
            ->assertOk()
            ->assertSee('Directorate Manager')
            ->assertSee('Dr. Sami Haddad')
            ->assertSee('Ph.D. in Computer Science')
            ->assertSee('Damascus University')
            ->assertSee('mailto:sami.haddad@spu.edu.sy', false)
            ->assertSee('tel:+963111234567', false)
            ->assertSee('/en/about/profile/sami-haddad', false);

        $this->get('/ar/about/directorates/it-services')
            ->assertOk()
            ->assertSee('مدير المديرية')
            ->assertSee('د. سامي حداد')
            ->assertSee('دكتوراه في علوم الحاسوب')
            ->assertSee('المؤهلات العلمية');
    }

    public function test_an_unpublished_manager_profile_is_not_shown(): void
    {
        $manager = $this->managerProfile(published: false);
        $this->publishDirectorate(function (array $payload) use ($manager): array {
            $payload['manager_member_id'] = (int) $manager->getKey();

            return $payload;
        });

        $this->get('/en/about/directorates/it-services')
            ->assertOk()
            ->assertDontSee('Directorate Manager')
            ->assertDontSee('Dr. Sami Haddad');
    }

    public function test_task_groups_round_trip_through_draft_and_publish(): void
    {
        $this->publishDirectorate(function (array $payload): array {
            $payload['translations']['en']['task_groups_json'] = [
                ['title' => 'Edited group', 'summary' => 'Intro line', 'items' => [
                    ['title' => 'First task', 'body' => 'What it covers'],
                    ['title' => '', 'body' => 'dropped: no title'],
                ]],
                ['title' => '', 'summary' => '', 'items' => []],
            ];

            return $payload;
        });

        $directorate = Directorate::query()->where('slug', 'it-services')->with('translations')->firstOrFail();
        $this->assertSame(
            [['title' => 'Edited group', 'summary' => 'Intro line', 'items' => [['title' => 'First task', 'body' => 'What it covers']]]],
            $directorate->translations->firstWhere('locale', 'en')?->task_groups_json,
        );

        $this->get('/en/about/directorates/it-services')
            ->assertOk()
            ->assertSee('Edited group')
            ->assertSee('Intro line')
            ->assertSee('First task')
            ->assertDontSee('dropped: no title');
    }

    public function test_publishing_rejects_a_missing_manager_profile(): void
    {
        $directorate = Directorate::query()->where('slug', 'it-services')->firstOrFail();
        $targetKey = 'entity.directorate.'.$directorate->getKey();
        $payload = app(AboutEntityCmsServiceInterface::class)->getStoredData($targetKey)?->payload ?? [];
        $payload['manager_member_id'] = 999999;

        $this->assertArrayHasKey('manager_member_id', app(AboutEntityCmsServiceInterface::class)->publishErrors($targetKey, $payload));
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $edit */
    private function publishDirectorate(callable $edit): void
    {
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $directorate = Directorate::query()->where('slug', 'it-services')->firstOrFail();
        $targetKey = 'entity.directorate.'.$directorate->getKey();
        $workflow = app(CmsWorkflowServiceInterface::class);

        $payload = app(AboutEntityCmsServiceInterface::class)->getStoredData($targetKey)?->payload;
        $this->assertIsArray($payload);

        $workflow->saveDraft($targetKey, $edit($payload), (int) $author->getKey());
        $this->assertTrue($workflow->publish($targetKey, (int) $author->getKey()));
    }

    private function managerProfile(bool $published): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => 'sami-haddad',
            'faculty_id' => null,
            'email' => 'sami.haddad@spu.edu.sy',
            'phone' => '+963 11 123 4567',
            'is_enabled' => true,
            'publication_status' => $published ? PublicationStatus::Published->value : PublicationStatus::Draft->value,
            'published_at' => $published ? now()->subDay() : null,
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => 'Dr. Sami Haddad']);
        $member->translations()->create(['locale' => 'ar', 'full_name' => 'د. سامي حداد']);

        $education = $member->educations()->create(['sort_order' => 1, 'is_enabled' => true]);
        $education->translations()->create(['locale' => 'en', 'degree' => 'Ph.D. in Computer Science', 'institution' => 'Damascus University', 'year_end' => 2012]);
        $education->translations()->create(['locale' => 'ar', 'degree' => 'دكتوراه في علوم الحاسوب', 'institution' => 'جامعة دمشق', 'year_end' => 2012]);

        return $member;
    }
}
