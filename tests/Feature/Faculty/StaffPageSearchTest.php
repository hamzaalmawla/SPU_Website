<?php

declare(strict_types=1);

namespace Tests\Feature\Faculty;

use App\Enums\PublicationStatus;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The teaching staff directory and a center's staff page search only the people
 * on that page; the directory's search works together with its faculty filter.
 */
final class StaffPageSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
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
