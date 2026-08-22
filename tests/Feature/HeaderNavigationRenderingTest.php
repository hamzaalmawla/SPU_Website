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
            ->assertSee('site-nav-dropdown-group-header', false)
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

    public function test_header_uses_the_reference_stacking_level(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('id="site-header" class="absolute top-0 z-[200]', false);

        $this->assertStringContainsString('z-[200]', (string) file_get_contents(resource_path('js/alpine/mobileNav.js')));
    }
}
