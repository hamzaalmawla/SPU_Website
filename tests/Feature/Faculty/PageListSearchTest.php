<?php

declare(strict_types=1);

namespace Tests\Feature\Faculty;

use App\Enums\PublicationStatus;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\Faculty\FacultyStudentProjectTranslation;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The search bars on a faculty's research and projects pages, the teaching staff
 * directory and a center's staff page look only through the list on that page,
 * and on the research and projects pages they work together with the year filter.
 */
final class PageListSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_faculty_research_search_finds_only_this_facultys_publications(): void
    {
        $this->publication('medicine', 2023, 'Cardiac Imaging Outcomes', 'نتائج تصوير القلب');
        $this->publication('medicine', 2024, 'Pediatric Asthma Survey', 'مسح الربو عند الأطفال');
        // Same word, another faculty: never part of the medicine page.
        $this->publication('dentistry', 2024, 'Dental Imaging Outcomes', 'نتائج تصوير الأسنان');

        $this->get('/en/faculties/medicine/research?q=imaging')
            ->assertOk()
            ->assertSee('Cardiac Imaging Outcomes')
            ->assertDontSee('Pediatric Asthma Survey')
            ->assertDontSee('Dental Imaging Outcomes');

        // Either language finds it.
        $this->get('/ar/faculties/medicine/research?q=imaging')
            ->assertOk()
            ->assertSee('نتائج تصوير القلب')
            ->assertDontSee('نتائج تصوير الأسنان');
    }

    public function test_faculty_research_year_filter_lists_this_facultys_years_and_works_with_the_search(): void
    {
        $this->publication('medicine', 2023, 'Cardiac Imaging Outcomes', 'نتائج تصوير القلب');
        $this->publication('medicine', 2024, 'Brain Imaging Atlas', 'أطلس تصوير الدماغ');
        $this->publication('medicine', 2024, 'Pediatric Asthma Survey', 'مسح الربو عند الأطفال');
        $this->publication('dentistry', 2019, 'Dental Imaging Outcomes', 'نتائج تصوير الأسنان');

        $this->get('/en/faculties/medicine/research')
            ->assertOk()
            ->assertSee('<option value="2024"', false)
            ->assertSee('<option value="2023"', false)
            ->assertDontSee('<option value="2019"', false);

        $this->get('/en/faculties/medicine/research?year=2024')
            ->assertOk()
            ->assertSee('Brain Imaging Atlas')
            ->assertSee('Pediatric Asthma Survey')
            ->assertDontSee('Cardiac Imaging Outcomes');

        $this->get('/en/faculties/medicine/research?year=2024&q=imaging')
            ->assertOk()
            ->assertSee('Brain Imaging Atlas')
            ->assertDontSee('Pediatric Asthma Survey')
            ->assertDontSee('Cardiac Imaging Outcomes')
            ->assertSee('<link rel="canonical" href="'.config('app.url').'/en/faculties/medicine/research?q=imaging&amp;year=2024">', false);

        $this->get('/en/faculties/medicine/research?q=nothing-like-this')
            ->assertOk()
            ->assertSee('No research matches your search');

        // A year the faculty has no paper in is ignored, not an empty page.
        $this->get('/en/faculties/medicine/research?year=2019')
            ->assertOk()
            ->assertSee('Cardiac Imaging Outcomes')
            ->assertSee('Brain Imaging Atlas');
    }

    public function test_faculty_projects_year_filter_works_with_the_search(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'pharmacy')->firstOrFail();
        FacultyStudentProject::query()->where('faculty_id', $faculty->getKey())->delete();
        $this->project($faculty, 'tablet-coating', 'Tablet Coating Study', '2022-2023');
        $this->project($faculty, 'tablet-dissolution', 'Tablet Dissolution Model', '2023-2024');
        $this->project($faculty, 'herbal-extracts', 'Herbal Extracts Review', '2023-2024');

        $this->get('/en/faculties/pharmacy/projects')
            ->assertOk()
            ->assertSee('<option value="2023/2024"', false)
            ->assertSee('<option value="2022/2023"', false);

        $this->get('/en/faculties/pharmacy/projects?year=2023/2024')
            ->assertOk()
            ->assertSee('Tablet Dissolution Model')
            ->assertSee('Herbal Extracts Review')
            ->assertDontSee('Tablet Coating Study');

        $this->get('/en/faculties/pharmacy/projects?year=2023/2024&q=tablet')
            ->assertOk()
            ->assertSee('Tablet Dissolution Model')
            ->assertDontSee('Herbal Extracts Review')
            ->assertDontSee('Tablet Coating Study');
    }

    public function test_staff_directory_search_looks_through_its_people_and_keeps_the_faculty_filter(): void
    {
        $this->member('medicine', 'zubaida-haddad', 'Zubaida Haddad', 'زبيدة حداد');
        $this->member('medicine', 'omar-khalil', 'Omar Khalil', 'عمر خليل');
        $this->member('dentistry', 'omar-nasser', 'Omar Nasser', 'عمر ناصر');

        $this->get('/en/about/directorates/staff?q=zubaida')
            ->assertOk()
            ->assertSee('Zubaida Haddad')
            ->assertDontSee('Omar Khalil')
            ->assertSee('1 results');

        // A name typed in the other language finds the person too.
        $this->get('/ar/about/directorates/staff?q=Haddad')
            ->assertOk()
            ->assertSee('زبيدة حداد')
            ->assertDontSee('عمر خليل');

        $this->get('/en/about/directorates/staff?q=omar&faculty=dentistry')
            ->assertOk()
            ->assertSee('Omar Nasser')
            ->assertDontSee('Omar Khalil')
            ->assertSee('value="omar"', false);
    }

    public function test_center_staff_page_has_a_search_over_its_own_cards(): void
    {
        $member = FacultyMember::query()->create([
            'slug' => 'unit-teacher',
            'center_slug' => 'requirements-unit',
            'sort_order' => 1,
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => 'Unit Teacher', 'position' => 'Lecturer']);
        $member->translations()->create(['locale' => 'ar', 'full_name' => 'مدرس الوحدة', 'position' => 'محاضر']);

        $this->get('/en/centers/requirements-unit/staff')
            ->assertOk()
            ->assertSee('x-data="facultyMembers"', false)
            ->assertSee('Search by name or position')
            ->assertSee('data-search="Unit Teacher Lecturer"', false);
    }

    private function publication(string $facultySlug, int $year, string $englishTitle, string $arabicTitle): void
    {
        $faculty = Faculty::query()->where('public_slug', $facultySlug)->firstOrFail();
        $member = FacultyMember::query()->create([
            'slug' => 'researcher-'.$facultySlug.'-'.md5($englishTitle),
            'faculty_id' => (int) $faculty->getKey(),
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        foreach (['ar' => 'باحث اختباري', 'en' => 'Test Researcher'] as $locale => $name) {
            FacultyMemberTranslation::query()->create([
                'faculty_member_id' => (int) $member->getKey(),
                'locale' => $locale,
                'full_name' => $name,
            ]);
        }
        $publication = ResearchPublication::query()->create([
            'faculty_member_id' => (int) $member->getKey(),
            'published_at' => now()->subDay(),
            'publication_year' => $year,
            'is_enabled' => true,
        ]);
        foreach (['ar' => $arabicTitle, 'en' => $englishTitle] as $locale => $title) {
            ResearchPublicationTranslation::query()->create([
                'research_publication_id' => (int) $publication->getKey(),
                'locale' => $locale,
                'title' => $title,
            ]);
        }

        Cache::flush();
    }

    private function project(Faculty $faculty, string $slug, string $title, string $year): void
    {
        $project = FacultyStudentProject::query()->create([
            'faculty_id' => $faculty->getKey(),
            'slug' => $slug,
            'is_enabled' => true,
            'sort_order' => 1,
        ]);
        FacultyStudentProjectTranslation::query()->create([
            'faculty_student_project_id' => $project->getKey(),
            'locale' => 'en',
            'title' => $title,
            'body_json' => ['Academic year: '.$year],
        ]);

        Cache::flush();
    }

    private function member(string $facultySlug, string $slug, string $nameEn, string $nameAr): void
    {
        $faculty = Faculty::query()->where('public_slug', $facultySlug)->firstOrFail();
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => (int) $faculty->getKey(),
            'sort_order' => 1,
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $nameEn, 'position' => 'Lecturer']);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $nameAr, 'position' => 'محاضر']);

        Cache::flush();
    }
}
