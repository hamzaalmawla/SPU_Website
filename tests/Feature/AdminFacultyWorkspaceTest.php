<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageDentistryFaculty;
use App\Models\Cms\CmsDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminFacultyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_faculty_workspace_uses_task_navigation_without_medicine_label_leak(): void
    {
        $this->actingAs($this->editor(), 'web')
            ->withSession(['admin_locale' => 'en'])
            ->get('/admin/manage-dentistry-faculty')
            ->assertOk()
            ->assertSee('Which faculty area do you want to manage?')
            ->assertSee('Faculty homepage')
            ->assertSee('Study plan')
            ->assertDontSee('Medicine Target');
    }

    public function test_study_plan_uses_human_prerequisite_selector_and_hides_reverse_graph_input(): void
    {
        $this->actingAs($this->editor(), 'web')
            ->withSession(['admin_locale' => 'en'])
            ->get('/admin/manage-dentistry-faculty?target=facilities.dentistry.study_plan')
            ->assertOk()
            ->assertSee('Courses by term')
            ->assertSee('Prerequisite courses')
            ->assertSee('Choose courses students must complete')
            ->assertDontSee('Opens Course IDs');
    }

    public function test_faculty_workspace_loads_requested_task_from_url(): void
    {
        $this->actingAs($this->editor(), 'web');

        Livewire::withQueryParams(['target' => 'facilities.dentistry.study_plan'])
            ->test(ManageDentistryFaculty::class)
            ->assertSet('data.target_key', 'facilities.dentistry.study_plan');
    }

    public function test_study_plan_department_and_term_are_direct_navigation_links(): void
    {
        $this->actingAs($this->editor(), 'web');

        $component = Livewire::withQueryParams(['target' => 'facilities.dentistry.study_plan'])
            ->test(ManageDentistryFaculty::class);
        $departments = $component->instance()->getStudyPlanDepartmentNavigation();
        $terms = $component->instance()->getStudyPlanTermNavigation();

        $this->assertNotEmpty($departments);
        $this->assertNotEmpty($terms);
        $this->assertStringContainsString('department=', $departments[0]['url']);
        $this->assertStringContainsString('term=', $terms[0]['url']);
    }

    public function test_study_plan_editor_only_hydrates_the_selected_term(): void
    {
        $this->actingAs($this->editor(), 'web');

        $component = Livewire::withQueryParams(['target' => 'facilities.dentistry.study_plan'])
            ->test(ManageDentistryFaculty::class);
        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $selectedTermId = $data['study_plan_term_id'] ?? null;
        $arabicTerms = array_values($data['ar_content']['payload']['plan']['terms'] ?? []);
        $englishTerms = array_values($data['en_content']['payload']['plan']['terms'] ?? []);
        $allTermCount = count($component->instance()->getStudyPlanTermNavigation());

        $this->assertNotEmpty($selectedTermId);
        $this->assertGreaterThan(1, $allTermCount);
        $this->assertCount(1, $arabicTerms);
        $this->assertCount(1, $englishTerms);
        $this->assertSame((string) $selectedTermId, (string) ($arabicTerms[0]['id'] ?? ''));
        $this->assertSame((string) $selectedTermId, (string) ($englishTerms[0]['id'] ?? ''));

        $component->call('save');

        $draft = CmsDraft::query()->where('target_key', 'facilities.dentistry.study_plan')->latest('id')->firstOrFail();
        $departmentId = (string) ($data['study_plan_department_id'] ?? '');
        $savedDepartment = collect($draft->payload_json['translations']['ar']['payload']['plan']['departments'] ?? [])
            ->firstWhere('id', $departmentId);

        $this->assertIsArray($savedDepartment);
        $this->assertCount($allTermCount, $savedDepartment['terms'] ?? []);
    }

    public function test_arabic_laboratory_editor_has_localized_primary_controls(): void
    {
        $this->actingAs($this->editor(), 'web')
            ->withSession(['admin_locale' => 'ar'])
            ->get('/admin/manage-dentistry-faculty?target=facilities.dentistry.labs')
            ->assertOk()
            ->assertSee('المخابر')
            ->assertSee('اسم المخبر')
            ->assertSee('المشرف أو المدرس')
            ->assertSee('اختيار أو رفع ملف')
            ->assertDontSee('Choose / Upload')
            ->assertDontSee('Subpage Content');
    }

    private function editor(): User
    {
        return User::factory()->create([
            'role_slug' => 'editor',
            'is_locked' => false,
        ]);
    }
}
