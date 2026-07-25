<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageCampusLife;
use App\Filament\Pages\ManageJobBoard;
use App\Models\Cms\CmsDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminJobsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_job_board_uses_one_bilingual_vacancy_editor(): void
    {
        $this->actingAs($this->editor(), 'web')
            ->withSession(['admin_locale' => 'en'])
            ->get('/admin/manage-job-board')
            ->assertOk()
            ->assertSee('Manage job opportunities')
            ->assertSee('Create each vacancy once')
            ->assertSee('Arabic content')
            ->assertSee('English content')
            ->assertSee('Accept online job applications')
            ->assertDontSee('Choose a Campus Life page')
            ->assertDontSee('Page / Subpage')
            ->assertDontSee('Job Catalog')
            ->assertDontSee('Course ID');
    }

    public function test_job_board_task_is_loaded_from_the_url(): void
    {
        $this->actingAs($this->editor(), 'web');

        Livewire::test(ManageJobBoard::class)
            ->assertSet('activeTargetKey', 'campus_life.jobs')
            ->assertSet('data.target_key', 'campus_life.jobs');
    }

    public function test_faculty_editor_cannot_open_global_job_board_workspace(): void
    {
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'dentistry',
            'is_locked' => false,
        ]);

        $this->actingAs($facultyEditor, 'web')
            ->get('/admin/manage-job-board')
            ->assertForbidden();
    }

    public function test_campus_life_selector_omits_job_board_while_dedicated_route_keeps_it(): void
    {
        $this->actingAs($this->editor(), 'web');

        $campusComponent = Livewire::test(ManageCampusLife::class);
        $method = new \ReflectionMethod(ManageCampusLife::class, 'targetOptions');
        $method->setAccessible(true);
        /** @var array<string, string> $options */
        $options = $method->invoke($campusComponent->instance());

        $this->assertArrayNotHasKey('campus_life.jobs', $options);

        Livewire::withQueryParams(['target' => 'campus_life.jobs'])
            ->test(ManageCampusLife::class)
            ->assertSet('activeTargetKey', 'campus_life.landing')
            ->assertSet('data.target_key', 'campus_life.landing')
            ->assertDontSee('Target Schema Pending');

        Livewire::test(ManageJobBoard::class)
            ->assertSet('activeTargetKey', 'campus_life.jobs')
            ->assertSet('data.target_key', 'campus_life.jobs');
    }

    public function test_closed_job_cannot_accept_applications_and_deadline_cannot_precede_posting(): void
    {
        $this->actingAs($this->editor(), 'web');

        $component = Livewire::test(ManageJobBoard::class);
        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $vacancyKey = array_key_first($data['jobs_workspace']['vacancies'] ?? []);
        $this->assertNotNull($vacancyKey);
        $prefix = 'data.jobs_workspace.vacancies.'.$vacancyKey;

        $component
            ->set($prefix.'.applicationEligible', true)
            ->set($prefix.'.status', 'closed')
            ->assertSet($prefix.'.applicationEligible', false)
            ->set($prefix.'.postedDate', '2026-07-20')
            ->set($prefix.'.closeDate', '2026-07-19')
            ->call('save')
            ->assertHasErrors([$prefix.'.closeDate' => 'after_or_equal']);
    }

    public function test_saving_bilingual_vacancy_keeps_shared_identity_in_both_locales(): void
    {
        $this->actingAs($this->editor(), 'web');

        $component = Livewire::test(ManageJobBoard::class);
        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $vacancyKey = array_key_first($data['jobs_workspace']['vacancies'] ?? []);

        $this->assertNotNull($vacancyKey);

        $component
            ->set('data.jobs_workspace.vacancies.'.$vacancyKey.'.title_en', 'Edited Bilingual Vacancy')
            ->set('data.jobs_workspace.vacancies.'.$vacancyKey.'.title_ar', 'فرصة عمل ثنائية اللغة')
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'campus_life.jobs')->latest('id')->firstOrFail();
        $arJobs = collect($draft->payload_json['translations']['ar']['jobs'] ?? []);
        $enJobs = collect($draft->payload_json['translations']['en']['jobs'] ?? []);

        $this->assertSame($arJobs->pluck('id')->all(), $enJobs->pluck('id')->all());
        $this->assertSame('فرصة عمل ثنائية اللغة', $arJobs->first()['title'] ?? null);
        $this->assertSame('Edited Bilingual Vacancy', $enJobs->first()['title'] ?? null);
    }

    private function editor(): User
    {
        return User::factory()->create([
            'role_slug' => 'editor',
            'is_locked' => false,
        ]);
    }
}
