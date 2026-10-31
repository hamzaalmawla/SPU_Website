<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageDentistryFaculty;
use App\Filament\Resources\FacultyMemberResource\Pages\CreateFacultyMember;
use App\Models\Faculty\Faculty;
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

    public function test_faculty_workspace_loads_requested_task_from_url(): void
    {
        $this->actingAs($this->editor(), 'web');

        Livewire::withQueryParams(['target' => 'facilities.dentistry.study_plan'])
            ->test(ManageDentistryFaculty::class)
            ->assertSet('data.target_key', 'facilities.dentistry.study_plan');
    }

    public function test_members_task_links_to_contextual_member_management(): void
    {
        $this->actingAs($this->editor(), 'web')
            ->withSession(['admin_locale' => 'en'])
            ->get('/admin/manage-dentistry-faculty?target=facilities.dentistry.members')
            ->assertOk()
            ->assertSee('Manage faculty members')
            ->assertSee('/admin/faculty-members?faculty_scope=dentistry', false)
            ->assertSee('/admin/faculty-members/create?faculty_scope=dentistry', false);
    }

    public function test_contextual_member_create_preselects_the_faculty(): void
    {
        $this->actingAs($this->editor(), 'web');
        $dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();

        Livewire::withQueryParams(['faculty_scope' => 'dentistry'])
            ->test(CreateFacultyMember::class)
            ->assertSet('data.faculty_id', (int) $dentistry->getKey());
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
