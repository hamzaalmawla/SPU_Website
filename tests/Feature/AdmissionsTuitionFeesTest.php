<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Filament\Pages\ManageAdmissions;
use App\Models\Cms\CmsDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdmissionsTuitionFeesTest extends TestCase
{
    use RefreshDatabase;

    private const FEE_FILE = 'database/seeders/Data/admissions/tuition_fees_2026_2027.json';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_an_editor_edits_one_fee_row_per_faculty_and_publishes_it_in_both_languages(): void
    {
        $this->actingAs($this->superAdmin(), 'web');

        $component = Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.tuition')
            ->call('loadTarget', 'admissions.tuition')
            ->assertSee(__('admin_pages.admissions.fees_by_faculty'))
            ->assertSee(__('admin_pages.admissions.add_faculty_fee_row'))
            ->assertDontSee(__('admin_pages.admissions.table_headers'));

        $component
            ->set('data.tuition_fees', [
                'medicine' => [
                    'faculty_ar' => 'الطب',
                    'faculty_en' => 'Medicine',
                    'scientific_average' => '80',
                    'vocational_average' => '',
                    'literary_average' => '',
                    'credit_hours' => '251',
                    'syrian_fee' => '18000',
                    'foreign_fee' => '350',
                ],
                'business' => [
                    'faculty_ar' => 'العلوم الإدارية',
                    'faculty_en' => 'Administrative Sciences',
                    'scientific_average' => '55',
                    'vocational_average' => '55',
                    'literary_average' => '50',
                    'credit_hours' => '134',
                    'syrian_fee' => '7000',
                    'foreign_fee' => '150',
                ],
            ])
            ->set('data.ar_tuition.fee_unit', 'للساعة المعتمدة')
            ->set('data.en_tuition.fee_unit', 'per credit hour')
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'admissions.tuition')->latest('id')->firstOrFail();
        $arabicRows = $draft->payload_json['translations']['ar']['feeRows'];
        $englishRows = $draft->payload_json['translations']['en']['feeRows'];

        $this->assertSame(['faculty' => 'الطب', 'scientificAverage' => 80, 'creditHours' => 251, 'syrianFee' => 18000, 'foreignFee' => 350], $arabicRows[0]);
        $this->assertSame('Medicine', $englishRows[0]['faculty']);
        $this->assertSame(50, $englishRows[1]['literaryAverage']);
        $this->assertArrayNotHasKey('tableHeaders', $draft->payload_json['translations']['en']);

        // Reopening the page shows the same single row per faculty.
        $reloaded = Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.tuition')
            ->call('loadTarget', 'admissions.tuition');
        $rows = array_values($reloaded->get('data.tuition_fees'));
        $this->assertCount(2, $rows);
        $this->assertSame(['الطب', 'Medicine', '18000', '350'], [$rows[0]['faculty_ar'], $rows[0]['faculty_en'], $rows[0]['syrian_fee'], $rows[0]['foreign_fee']]);

        $reloaded->call('publish')->assertHasNoFormErrors();

        $this->get('/ar/admissions/tuition')
            ->assertOk()
            ->assertSeeInOrder(['الطب', 'الفرع العلمي', '80%', '251', '18,000 ل.س.ج', '350 $'])
            ->assertSeeInOrder(['العلوم الإدارية', 'الفرع العلمي', '55%', 'الفرع المهني', '55%', 'الفرع الأدبي', '50%', '134', '7,000 ل.س.ج', '150 $'])
            ->assertSee('للساعة المعتمدة')
            ->assertSee('الطلبة السوريون ومن في حكمهم');

        $this->get('/en/admissions/tuition')
            ->assertOk()
            ->assertSeeInOrder(['Medicine', 'Scientific', '80%', '251', '18,000 new SYP', '350 USD'])
            ->assertSee('per credit hour')
            ->assertSee('Arab and foreign students');
    }

    public function test_a_fee_row_without_any_certificate_track_cannot_be_published(): void
    {
        $this->actingAs($this->superAdmin(), 'web');

        Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.tuition')
            ->call('loadTarget', 'admissions.tuition')
            ->set('data.tuition_fees', [
                'row' => [
                    'faculty_ar' => 'الطب',
                    'faculty_en' => 'Medicine',
                    'scientific_average' => '',
                    'vocational_average' => '',
                    'literary_average' => '',
                    'credit_hours' => '251',
                    'syrian_fee' => '18000',
                    'foreign_fee' => '350',
                ],
            ])
            ->call('publish');

        $notification = collect(session('filament.notifications'))->last();
        $this->assertSame(__('admin_pages.admissions.publish_failed'), $notification['title'] ?? null);
        $this->assertStringContainsString(__('admin_pages.admissions.at_least_one_track'), (string) ($notification['body'] ?? ''));
        $this->get('/en/admissions/tuition')->assertNotFound();

        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(AdmissionsPageServiceInterface::class)->getEditablePayload('admissions.tuition');
        $payload['translations']['en']['feeRows'] = [['faculty' => 'Medicine', 'creditHours' => 251, 'syrianFee' => 18000, 'foreignFee' => 350]];
        $payload['translations']['ar']['feeRows'] = [['faculty' => 'الطب', 'creditHours' => 251, 'syrianFee' => 18000, 'foreignFee' => 350]];

        $this->assertFalse($workflow->readiness('admissions.tuition', $payload)->isReady);
    }

    public function test_the_approved_2026_2027_fee_table_imports_and_publishes_once(): void
    {
        $user = (string) $this->superAdmin()->id;

        $this->artisan('admissions:import-tuition-fees', ['file' => base_path(self::FEE_FILE), '--user' => $user])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        $this->get('/en/admissions/tuition')->assertNotFound();

        $this->artisan('admissions:import-tuition-fees', ['file' => base_path(self::FEE_FILE), '--user' => $user, '--publish' => true])
            ->expectsOutputToContain('Published the tuition page with 7 fee row(s).')
            ->assertSuccessful();

        $this->get('/ar/admissions/tuition')
            ->assertOk()
            ->assertSeeInOrder(['الطب', '80%', '251', '18,000 ل.س.ج', '350 $'])
            ->assertSeeInOrder(['هندسة البترول', 'الفرع العلمي', '65%', 'الفرع المهني', '75%', '180', '10,000 ل.س.ج', '200 $'])
            ->assertSeeInOrder(['العلوم الإدارية', '55%', '55%', '50%', '134', '7,000 ل.س.ج', '150 $'])
            ->assertSee('رسوم مفاضلة العام الدراسي 2026 - 2027');

        $this->get('/en/admissions/tuition')
            ->assertOk()
            ->assertSeeInOrder(['Dentistry', '75%', '185', '16,000 new SYP', '300 USD'])
            ->assertSeeInOrder(['Building and Construction Technology Engineering', '65%', '180', '10,000 new SYP', '200 USD'])
            ->assertSee('per credit hour');

        $this->artisan('admissions:import-tuition-fees', ['file' => base_path(self::FEE_FILE), '--user' => $user, '--publish' => true])
            ->expectsOutputToContain('UNCHANGED')
            ->assertSuccessful();
    }

    public function test_the_import_leaves_an_editors_unpublished_draft_alone(): void
    {
        $author = $this->superAdmin();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(AdmissionsPageServiceInterface::class)->getEditablePayload('admissions.tuition');
        $payload['translations']['en']['title'] = 'Editor work in progress';
        $workflow->saveDraft('admissions.tuition', $payload, (int) $author->id);

        $this->artisan('admissions:import-tuition-fees', ['file' => base_path(self::FEE_FILE), '--user' => (string) $author->id, '--publish' => true])
            ->expectsOutputToContain('SKIPPED')
            ->assertSuccessful();

        $this->get('/en/admissions/tuition')->assertNotFound();
        $this->assertSame('Editor work in progress', $workflow->latestEditableDraftForReport('admissions.tuition')['payload']['translations']['en']['title'] ?? null);
    }

    private function superAdmin(): User
    {
        return User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }
}
