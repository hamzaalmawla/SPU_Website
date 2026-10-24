<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Pages\ManageDentistryFaculty;
use App\Models\Cms\CmsDraft;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A faculty project's supervisors are faculty profiles: the project pages show
 * each one's name and photo and link to the profile. The projects search finds
 * a project by its Arabic or English title, team or supervisor on either site,
 * and tolerates a near spelling.
 */
final class FacultyProjectSupervisorsTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'facilities.dentistry.projects';

    private User $admin;

    private FacultyMember $first;

    private FacultyMember $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Cache::flush();
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        $this->first = $this->member('supervisor-one-test', 'Dr. Lina Supervisor', 'د. لينا المشرفة', (int) $dentistry->getKey());
        $this->second = $this->member('supervisor-two-test', 'Dr. Omar Advisor', 'د. عمر المستشار', (int) $dentistry->getKey());
    }

    public function test_the_project_pages_show_the_supervisor_profiles_with_links(): void
    {
        $this->publishProjects();

        $this->get('/en/faculties/dentistry/projects')
            ->assertOk()
            ->assertSee('Supervisors')
            ->assertSee('Dr. Lina Supervisor')
            ->assertSee('Dr. Omar Advisor')
            ->assertSee('/en/about/profile/supervisor-one-test', false)
            ->assertSee('/en/about/profile/supervisor-two-test', false)
            // A project with only the imported text keeps showing it.
            ->assertSee('Dr. Old Text');

        // The English item picked no one: it uses the Arabic item's supervisors.
        $this->get('/en/faculties/dentistry/projects/smile-design')
            ->assertOk()
            ->assertSee('Supervisors')
            ->assertSee('Dr. Lina Supervisor')
            ->assertSee('/en/about/profile/supervisor-two-test', false);

        $this->get('/ar/faculties/dentistry/projects/smile-design')
            ->assertOk()
            ->assertSee('المشرفون')
            ->assertSee('د. لينا المشرفة')
            ->assertSee('/ar/about/profile/supervisor-one-test', false);

        // A hidden profile is left out.
        $this->second->forceFill(['is_enabled' => false])->save();
        Cache::flush();
        $this->get('/en/faculties/dentistry/projects/smile-design')
            ->assertOk()
            ->assertDontSee('Dr. Omar Advisor')
            ->assertSee('Dr. Lina Supervisor');
    }

    public function test_the_projects_search_finds_a_project_in_either_language_and_with_a_near_spelling(): void
    {
        $this->publishProjects();

        $searches = [
            ['en', 'Digital Smile'],
            ['en', 'تصميم الابتسامة'],
            ['ar', 'Digital Smile'],
            ['ar', 'الابتسامه الرقمي'],
            ['en', 'Digtal Smile'],
            ['en', 'smile digital'],
            // A supervisor's name, in the other language, and a team member.
            ['en', 'لينا المشرفة'],
            ['ar', 'Omar Advisor'],
            ['en', 'Rana Kassem'],
        ];

        foreach ($searches as [$locale, $query]) {
            $smileTitle = $locale === 'ar' ? 'تصميم الابتسامة الرقمي' : 'Digital Smile Design';
            $implantTitle = $locale === 'ar' ? 'دليل الزرعات الجراحي' : 'Surgical Implant Guide';
            $this->get('/'.$locale.'/faculties/dentistry/projects?q='.rawurlencode($query))
                ->assertOk()
                ->assertSee($smileTitle)
                ->assertDontSee($implantTitle);
        }

        $this->get('/en/faculties/dentistry/projects?q='.rawurlencode('Zygomatic'))
            ->assertOk()
            ->assertDontSee('Digital Smile Design')
            ->assertDontSee('Surgical Implant Guide');
    }

    public function test_the_project_editor_saves_the_picked_supervisor_profiles(): void
    {
        $this->publishProjects();
        $this->actingAs($this->admin, 'web');

        $editor = Livewire::test(ManageDentistryFaculty::class)
            ->set('data.target_key', self::TARGET)
            ->set('data.record_search', 'Surgical')
            ->call('loadTarget', self::TARGET)
            ->assertSee('المشرفون');

        /** @var array<string, mixed> $data */
        $data = $editor->get('data');
        $key = array_key_first($data['en_content']['items'] ?? []);
        $this->assertNotNull($key);
        $editor
            ->set('data.en_content.items.'.$key.'.supervisorMemberIds', [(string) $this->second->getKey(), (string) $this->first->getKey()])
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', self::TARGET)->latest('id')->firstOrFail();
        $saved = collect($draft->payload_json['translations']['en']['items'])->firstWhere('slug', 'implant-guide');
        $this->assertSame([(int) $this->second->getKey(), (int) $this->first->getKey()], $saved['supervisorMemberIds']);
        $this->assertSame('Dr. Old Text', $saved['supervisor']);
    }

    private function publishProjects(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);
        $projects = [
            'ar' => [
                ['slug' => 'smile-design', 'title' => 'تصميم الابتسامة الرقمي', 'team' => 'رنا قاسم', 'supervisorMemberIds' => [(int) $this->first->getKey(), (int) $this->second->getKey()]],
                ['slug' => 'implant-guide', 'title' => 'دليل الزرعات الجراحي', 'team' => 'سامر علي', 'supervisor' => 'د. نص قديم'],
            ],
            'en' => [
                ['slug' => 'smile-design', 'title' => 'Digital Smile Design', 'team' => 'Rana Kassem'],
                ['slug' => 'implant-guide', 'title' => 'Surgical Implant Guide', 'team' => 'Samer Ali', 'supervisor' => 'Dr. Old Text'],
            ],
        ];
        foreach ($projects as $locale => $items) {
            $payload['translations'][$locale]['items'] = $items;
        }
        $workflow->saveDraft(self::TARGET, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish(self::TARGET, (int) $this->admin->getKey()));
        Cache::flush();
    }

    private function member(string $slug, string $en, string $ar, int $facultyId): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => $facultyId,
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $en]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $ar]);

        return $member;
    }
}
