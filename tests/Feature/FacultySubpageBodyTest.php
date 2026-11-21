<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every faculty subpage editor has Page intro › Body; the published text shows on
 * the page, whatever its layout (the shared hero, research, training, the study
 * plan), and only once on pages that place it in their own content.
 */
final class FacultySubpageBodyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    public function test_the_body_shows_on_each_kind_of_subpage(): void
    {
        $pages = [
            ['facilities.medicine.departments', '/faculties/medicine/departments'],
            ['facilities.medicine.labs', '/faculties/medicine/labs'],
            ['facilities.medicine.members', '/faculties/medicine/members'],
            ['facilities.medicine.research', '/faculties/medicine/research'],
            ['facilities.medicine.study_plan', '/faculties/medicine/study-plan'],
            // The council page's body is covered with its dean in FacultyCouncilTest.
        ];

        foreach ($pages as $index => [$target, $path]) {
            $this->publishBody($target, 'Intro body '.$index.' in English.', 'نص المقدمة '.$index.' بالعربية.');

            $this->get('/en'.$path)->assertOk()->assertSee('Intro body '.$index.' in English.');
            $this->get('/ar'.$path)->assertOk()->assertSee('نص المقدمة '.$index.' بالعربية.');
        }
    }

    public function test_the_body_shows_on_the_training_page(): void
    {
        // Training publishes only once its facts are verified, so the body is set on the page itself.
        $pharmacy = Faculty::query()->where('public_slug', 'pharmacy')->firstOrFail();
        $page = FacultyPage::query()->where('faculty_id', $pharmacy->getKey())->where('slug', 'training')->firstOrFail();

        foreach (['en' => 'Training intro body.', 'ar' => 'مقدمة صفحة التدريب.'] as $locale => $body) {
            $page->translations()->updateOrCreate(['locale' => $locale], ['body' => $body]);
        }

        $this->get('/en/faculties/pharmacy/training')->assertOk()->assertSee('Training intro body.');
        $this->get('/ar/faculties/pharmacy/training')->assertOk()->assertSee('مقدمة صفحة التدريب.');
    }

    public function test_the_master_program_shows_the_body_once(): void
    {
        $this->publishBody('facilities.artificial-intelligence.master-program', 'Master intro body.', 'مقدمة الماجستير.');

        $html = (string) $this->get('/en/faculties/artificial-intelligence/master-program')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Master intro body.'));
    }

    private function publishBody(string $target, string $english, string $arabic): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload($target);
        $payload['translations']['en']['body'] = $english;
        $payload['translations']['ar']['body'] = $arabic;

        $workflow->saveDraft($target, $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish($target, (int) $this->admin->getKey()), $target.' did not publish.');
    }
}
