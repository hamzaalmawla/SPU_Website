<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Person\FacultyMember;
use App\Models\Search\SearchDocument;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Site search finds the centers pages and each Requirements Unit course, as
 * visitors see them (published content, else the defaults), and follows
 * publishing, unpublishing and staff changes.
 */
final class CentersSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $this->artisan('search:index', ['--source' => 'centers'])->assertSuccessful();
        Cache::flush();
    }

    public function test_the_centers_pages_are_found_in_both_languages(): void
    {
        $this->get('/en/search?q='.rawurlencode('Requirements Unit'))->assertOk()
            ->assertSee('href="/en/centers/requirements-unit"', false);

        $this->get('/ar/search?q='.rawurlencode('وحدة المتطلبات'))->assertOk()
            ->assertSee('href="/ar/centers/requirements-unit"', false);

        // An Arabic query on the English site still finds the English page.
        $this->get('/en/search?q='.rawurlencode('المراكز والوحدات'))->assertOk()
            ->assertSee('href="/en/centers"', false);
    }

    public function test_a_course_is_found_by_its_name_or_a_faculty_code_and_links_to_its_place(): void
    {
        $this->get('/en/search?q=Sociology')->assertOk()
            ->assertSee('/en/centers/requirements-unit/elective-courses#course-sociology', false);

        $this->get('/en/search?q=UNRE103')->assertOk()
            ->assertSee('/en/centers/requirements-unit/required-courses#course-arabic-language', false);

        $this->get('/ar/search?q='.rawurlencode('مهارات الحاسوب'))->assertOk()
            ->assertSee('/ar/centers/requirements-unit/required-courses#course-computer-skills', false);
    }

    public function test_publishing_and_unpublishing_update_the_index(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(CenterCatalogServiceInterface::class)->defaultPayload('centers.requirements-unit.courses');
        $payload['courses'][0]['titleEn'] = 'Arabic Language and Literature';
        $workflow->saveDraft('centers.requirements-unit.courses', $payload, (int) $this->admin->getKey());
        $workflow->publish('centers.requirements-unit.courses', (int) $this->admin->getKey());
        Cache::flush();

        // The English title changed; the Arabic one did not.
        $this->assertSame(1, $this->documentsTitled('Arabic Language and Literature'));
        $this->get('/en/search?q=Literature')->assertOk()
            ->assertSee('#course-arabic-language', false);

        $workflow->unpublish('centers.requirements-unit.courses', (int) $this->admin->getKey());
        Cache::flush();

        $this->assertSame(0, $this->documentsTitled('Arabic Language and Literature'));
        $this->assertSame(1, $this->documentsTitled('Arabic Language'));
    }

    public function test_a_center_staff_change_updates_the_staff_page_document(): void
    {
        $member = FacultyMember::query()->create([
            'slug' => 'searchable-teacher',
            'center_slug' => 'requirements-unit',
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => 'Zenobia Quillfeather']);
        $member->translations()->create(['locale' => 'ar', 'full_name' => 'زنوبيا']);
        // The translations were written after the profile; one more save re-reads the staff page.
        $member->touch();
        Cache::flush();

        $this->get('/en/search?q=Quillfeather')->assertOk()
            ->assertSee('href="/en/centers/requirements-unit/staff"', false);
    }

    public function test_the_full_rebuild_is_idempotent_and_removes_courses_no_longer_listed(): void
    {
        $this->artisan('search:index')->assertSuccessful();
        $count = SearchDocument::query()->where('searchable_type', CmsTargetContent::class)->count();
        $this->assertGreaterThan(30, $count);

        $this->artisan('search:index', ['--source' => 'centers'])->assertSuccessful();
        $this->assertSame($count, SearchDocument::query()->where('searchable_type', CmsTargetContent::class)->count());

        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(CenterCatalogServiceInterface::class)->defaultPayload('centers.requirements-unit.courses');
        $payload['courses'] = array_values(array_filter($payload['courses'], fn (array $course): bool => $course['id'] !== 'sociology'));
        $workflow->saveDraft('centers.requirements-unit.courses', $payload, (int) $this->admin->getKey());
        $workflow->publish('centers.requirements-unit.courses', (int) $this->admin->getKey());

        $this->assertSame($count - 2, SearchDocument::query()->where('searchable_type', CmsTargetContent::class)->count());
        $this->assertFalse(SearchDocument::query()->where('url', 'like', '%#course-sociology')->exists());
    }

    private function documentsTitled(string $title): int
    {
        return SearchDocument::query()->where('searchable_type', CmsTargetContent::class)->where('locale', 'en')->where('title', $title)->count()
            + SearchDocument::query()->where('searchable_type', CmsTargetContent::class)->where('locale', 'ar')->where('title', $title)->count();
    }
}
