<?php

declare(strict_types=1);

namespace Tests\Feature\Faculty;

use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyPageTranslation;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\Faculty\FacultyStudentProjectTranslation;
use App\Models\Faculty\FacultyTranslation;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The search bar and year filter on a faculty's research and projects pages
 * narrow that faculty's own list: nothing from another faculty is ever added,
 * and search and year work together.
 */
final class FacultyListSearchTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $pharmacy;

    private Faculty $dentistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pharmacy = $this->faculty('pharmacy', 'كلية الصيدلة', 1);
        $this->dentistry = $this->faculty('dentistry', 'كلية طب الأسنان', 2);

        foreach ([$this->pharmacy, $this->dentistry] as $faculty) {
            $page = FacultyPage::create(['faculty_id' => $faculty->id, 'slug' => 'research', 'kind' => 'research', 'sort_order' => 1, 'is_enabled' => true]);
            FacultyPageTranslation::create(['faculty_page_id' => $page->id, 'locale' => 'ar', 'title' => 'أحدث الأبحاث']);
            FacultyPageTranslation::create(['faculty_page_id' => $page->id, 'locale' => 'en', 'title' => 'Latest Research']);
        }

        $this->publication($this->pharmacy, 'دراسة المضادات الحيوية', 'Antibiotics Study', 2024);
        $this->publication($this->pharmacy, 'تحليل المضادات الحيوية في الأطفال', 'Antibiotics in Children', 2022);
        $this->publication($this->pharmacy, 'كيمياء الأعشاب الطبية', 'Medicinal Herbs Chemistry', 2024);
        $this->publication($this->dentistry, 'المضادات الحيوية في طب الأسنان', 'Antibiotics in Dentistry', 2024);

        $this->project($this->pharmacy, 'project-alpha', 'مشروع الأدوية الذكية', 'أحمد علي', '2023/2024');
        $this->project($this->pharmacy, 'project-beta', 'تحليل الأدوية العشبية', 'سارة محمود', '2022 - 2023');
        $this->project($this->pharmacy, 'project-gamma', 'تطبيق تذكير الأدوية', 'ليلى حسن', '2022-2023');
        $this->project($this->dentistry, 'project-delta', 'الأدوية في عيادة الأسنان', 'خالد سمير', '2023-2024');
    }

    public function test_research_search_finds_only_this_faculty_publications(): void
    {
        $response = $this->get('/ar/faculties/pharmacy/research?q='.urlencode('المضادات الحيوية'));

        $response->assertOk();
        $response->assertSee('دراسة المضادات الحيوية');
        $response->assertSee('تحليل المضادات الحيوية في الأطفال');
        $response->assertDontSee('كيمياء الأعشاب الطبية');
        $response->assertDontSee('المضادات الحيوية في طب الأسنان');
        $response->assertSee('name="q"', false);
    }

    public function test_research_search_matches_the_other_language_title(): void
    {
        $this->get('/ar/faculties/pharmacy/research?q=herbs')
            ->assertOk()
            ->assertSee('كيمياء الأعشاب الطبية')
            ->assertDontSee('دراسة المضادات الحيوية');
    }

    public function test_research_year_filter_lists_this_faculty_years_and_works_with_search(): void
    {
        $page = $this->get('/ar/faculties/pharmacy/research');
        $page->assertOk();
        $page->assertSee('<option value="2024"', false);
        $page->assertSee('<option value="2022"', false);

        $this->get('/ar/faculties/pharmacy/research?year=2024')
            ->assertOk()
            ->assertSee('دراسة المضادات الحيوية')
            ->assertSee('كيمياء الأعشاب الطبية')
            ->assertDontSee('تحليل المضادات الحيوية في الأطفال')
            ->assertDontSee('المضادات الحيوية في طب الأسنان');

        $this->get('/ar/faculties/pharmacy/research?year=2024&q='.urlencode('المضادات'))
            ->assertOk()
            ->assertSee('دراسة المضادات الحيوية')
            ->assertDontSee('كيمياء الأعشاب الطبية')
            ->assertDontSee('تحليل المضادات الحيوية في الأطفال');
    }

    public function test_research_ignores_a_year_the_faculty_has_nothing_in(): void
    {
        $this->get('/ar/faculties/pharmacy/research?year=1999')
            ->assertOk()
            ->assertSee('دراسة المضادات الحيوية')
            ->assertSee('تحليل المضادات الحيوية في الأطفال');
    }

    public function test_research_search_with_no_match_says_so(): void
    {
        $this->get('/en/faculties/pharmacy/research?q=zzzz')
            ->assertOk()
            ->assertSee('No matching research')
            ->assertDontSee('Antibiotics Study');
    }

    public function test_projects_year_filter_lists_this_faculty_years(): void
    {
        $page = $this->get('/ar/faculties/pharmacy/projects');
        $page->assertOk();
        $page->assertSee('<option value="2023-2024"', false);
        $page->assertSee('<option value="2022-2023"', false);
        $this->assertSame(1, substr_count($page->getContent(), '<option value="2022-2023"'));
    }

    public function test_projects_year_filter_and_search_work_together(): void
    {
        $this->get('/ar/faculties/pharmacy/projects?year=2022-2023')
            ->assertOk()
            ->assertSee('تحليل الأدوية العشبية')
            ->assertSee('تطبيق تذكير الأدوية')
            ->assertDontSee('مشروع الأدوية الذكية')
            ->assertDontSee('الأدوية في عيادة الأسنان');

        $this->get('/ar/faculties/pharmacy/projects?year=2022-2023&q='.urlencode('ليلى'))
            ->assertOk()
            ->assertSee('تطبيق تذكير الأدوية')
            ->assertDontSee('تحليل الأدوية العشبية')
            ->assertDontSee('مشروع الأدوية الذكية');
    }

    public function test_projects_search_never_brings_another_faculty(): void
    {
        $this->get('/ar/faculties/pharmacy/projects?q='.urlencode('الأدوية'))
            ->assertOk()
            ->assertSee('مشروع الأدوية الذكية')
            ->assertDontSee('الأدوية في عيادة الأسنان');
    }

    public function test_each_search_and_year_is_its_own_cached_page(): void
    {
        $this->get('/ar/faculties/pharmacy/projects')->assertSee('مشروع الأدوية الذكية');
        $this->get('/ar/faculties/pharmacy/projects?year=2022-2023')->assertDontSee('مشروع الأدوية الذكية');
        $this->get('/ar/faculties/pharmacy/research')->assertSee('كيمياء الأعشاب الطبية');
        $this->get('/ar/faculties/pharmacy/research?q=antibiotics')->assertDontSee('كيمياء الأعشاب الطبية');
    }

    private function faculty(string $slug, string $name, int $order): Faculty
    {
        $faculty = Faculty::create([
            'slug' => $slug,
            'public_slug' => $slug,
            'faculty_scope_slug' => $slug,
            'is_enabled' => true,
            'sort_order' => $order,
        ]);

        FacultyTranslation::create(['faculty_id' => $faculty->id, 'locale' => 'ar', 'name' => $name]);
        FacultyTranslation::create(['faculty_id' => $faculty->id, 'locale' => 'en', 'name' => 'Faculty of '.ucfirst($slug)]);

        return $faculty;
    }

    private function publication(Faculty $faculty, string $arTitle, string $enTitle, int $year): void
    {
        $publication = ResearchPublication::create([
            'faculty_id' => $faculty->id,
            'publication_year' => $year,
            'published_at' => now()->setDate($year, 3, 1),
            'is_enabled' => true,
        ]);

        foreach (['ar' => $arTitle, 'en' => $enTitle] as $locale => $title) {
            ResearchPublicationTranslation::create([
                'research_publication_id' => $publication->id,
                'locale' => $locale,
                'title' => $title,
            ]);
        }
    }

    private function project(Faculty $faculty, string $slug, string $title, string $team, string $year): void
    {
        $project = FacultyStudentProject::create([
            'faculty_id' => $faculty->id,
            'slug' => $slug,
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        FacultyStudentProjectTranslation::create([
            'faculty_student_project_id' => $project->id,
            'locale' => 'ar',
            'title' => $title,
            'team' => $team,
            'body_json' => ['السنة الدراسية: '.$year],
        ]);
    }
}
