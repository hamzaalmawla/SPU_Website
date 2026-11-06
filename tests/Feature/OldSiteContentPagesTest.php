<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsPageTextImportServiceInterface;
use App\Filament\Pages\ManageCampusLife;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OldSiteContentPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['role_slug' => 'super_admin', 'is_locked' => false]);
    }

    public function test_the_organisational_chart_links_its_units_in_the_readers_language(): void
    {
        $this->import('org-chart');

        $this->get('/ar/about/organizational-structure')
            ->assertOk()
            ->assertSee('مجلس الأمناء')
            ->assertSee('نائب رئيس الجامعة للشؤون الإدارية والطلاب')
            ->assertSee('href="/ar/faculties/medicine"', false)
            ->assertSee('href="/ar/e-services/library"', false)
            ->assertSee('دائرة البيئة والاستدامة');
        $this->get('/en/about/organizational-structure')
            ->assertOk()
            ->assertSee('Board of Trustees')
            ->assertSee('href="/en/about/directorates/student-affairs"', false);
    }

    public function test_exam_instructions_and_general_rules_show_their_text(): void
    {
        $this->import('campus-rules');

        $this->get('/ar/campus-life/exam-instructions')
            ->assertOk()
            ->assertSee('يمنع إدخال (الساعات الذكية أو السماعات) إلى القاعة الامتحانية منعاً باتاً.')
            ->assertSee('أ.د. عبد الرزاق الحسين');
        $this->get('/en/campus-life/general-rules')
            ->assertOk()
            ->assertSee('When a blood donation certificate is required')
            ->assertSee('Original documents');
    }

    public function test_the_hospital_and_dental_pages_carry_the_old_sites_text(): void
    {
        $this->import('clinics');

        $this->get('/ar/campus-life/hospital')
            ->assertOk()
            ->assertSee('وحدة غسيل الكلى')
            ->assertSee('د. بيهس الوقاف')
            ->assertSee('الإنعاش القلبي الرئوي')
            ->assertDontSee('رعاية قلب شاملة، تشخيصات، وخيارات علاجية متقدمة.');
        $this->get('/en/campus-life/dental')
            ->assertOk()
            ->assertSee('Dr. Jihad Albardan')
            ->assertSee('106 dental units');
    }

    public function test_the_admin_editors_show_the_new_fields(): void
    {
        $this->actingAs($this->admin);

        $this->get(ManageCampusLife::getUrl(['target' => 'campus_life.hospital']))
            ->assertOk()
            ->assertSee(__('admin_pages.campus_life.clinical_tips'));
    }

    private function import(string $file): void
    {
        $results = app(CmsPageTextImportServiceInterface::class)->import(base_path('database/seeders/Data/legacy/page_texts/'.$file.'.json'), (string) $this->admin->getKey(), true);

        foreach ($results as $result) {
            self::assertStringStartsWith('published', $result['result'], $result['target']);
        }
    }
}
