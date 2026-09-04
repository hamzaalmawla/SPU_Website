<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HeaderNavigationRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $research = app(ResearchPageServiceInterface::class);

        foreach (['research.index', 'research.publications', 'research.themes'] as $targetKey) {
            $workflow->saveDraft($targetKey, $research->getEditablePayload($targetKey), (int) $author->getKey());
            $this->assertTrue($workflow->publish($targetKey, (int) $author->getKey()));
        }
    }

    public function test_header_renders_frontend_navigation_additions(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('Accreditation')
            ->assertSee('Why SPU')
            ->assertSee('Filling Vacancies')
            ->assertSee('Graduation &amp; National Exams', false)
            ->assertSee('Research Themes')
            ->assertSee('Machine Learning in Pharmaceutical Quality Control')
            ->assertSee('Job Board')
            ->assertSee('Damascus Research Center')
            ->assertSee('Announcements')
            ->assertSee('Media Gallery')
            // The research menu was given a flat dropdown with its own treatment
            // in "fix: repair public navigation and research profiles", which
            // dropped the grouped header/featured markup -- research was the only
            // three-level menu, so that markup disappeared entirely rather than
            // moving elsewhere.
            //
            // That treatment is now conditional. --research pours the panel into
            // two 32rem columns, which reads well for a long flat list and badly
            // for a group box: the box cannot break across columns, so it takes
            // the first column alone and strands one link in the second above a
            // large gap. It is held back while any child still has children, and
            // the grouped markup comes back in its place -- which is also what
            // keeps the featured researcher profiles reachable from the header.
            ->assertSee('site-nav-dropdown-group', false)
            ->assertSee('site-nav-dropdown-featured', false)
            ->assertSee('/en/campus-life/career-development/jobs')
            ->assertSee('/en/research/publications/machine-learning-pharmaceutical-quality-control');
    }

    public function test_arabic_header_renders_frontend_navigation_additions(): void
    {
        $this->get('/ar')
            ->assertOk()
            ->assertSee('الاعتمادية')
            ->assertSee('لماذا SPU')
            ->assertSee('ملء الشواغر')
            ->assertSee('مجالات البحث')
            ->assertSee('لوحة الوظائف')
            ->assertSee('منشورات مركز دمشق للأبحاث')
            ->assertSee('الإعلانات')
            ->assertSee('معرض الوسائط');
    }

    public function test_header_is_stable_from_first_paint_and_uses_the_reference_stacking_level(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('id="site-header" class="fixed inset-x-0 top-0 z-[200]', false)
            ->assertSee('src="/images/icon-bars-outline.svg" :src="mobileToggleIcon()"', false);

        $this->assertStringNotContainsString('headerClass()', (string) file_get_contents(resource_path('views/public/layout/header.blade.php')));
    }
}
