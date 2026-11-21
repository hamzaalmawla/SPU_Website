<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Seo\SitemapServiceInterface;
use App\Models\Career\Alumni;
use App\Models\Career\AlumniTranslation;
use App\Models\Faculty\Department;
use App\Models\Faculty\DepartmentTranslation;
use App\Models\Faculty\Faculty;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class AlumniDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_localized_directory_supports_verified_filters_search_pagination_and_seo(): void
    {
        $medicine = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        $department = $this->departmentFor($medicine);

        for ($index = 1; $index <= 13; $index++) {
            $this->createAlumni(
                faculty: $medicine,
                nameEn: 'Global Medicine Alumni '.$index,
                nameAr: 'خريج الطب العالمي '.$index,
                year: 2026,
                department: $department,
                email: 'private-'.$index.'@example.test',
            );
        }

        $this->createAlumni(
            faculty: $dentistry,
            nameEn: 'Global Dentistry Alumni',
            nameAr: 'خريج طب الأسنان العالمي',
            year: 2025,
        );
        Cache::flush();

        $this->get('/en/alumni?faculty=medicine&year=2026&department='.$department->slug.'&page=2')
            ->assertOk()
            ->assertSee('Global Medicine Alumni 1')
            ->assertSee('dir="ltr"', false)
            ->assertSee('<link rel="canonical" href="'.config('app.url').'/en/alumni?', false)
            ->assertSee('hreflang="ar"', false)
            ->assertSee('/ar/alumni?', false)
            ->assertDontSee('private-1@example.test')
            ->assertDontSee('student-secret-1')
            ->assertDontSee('Global Dentistry Alumni');

        $this->get('/ar/alumni?q=%D8%AE%D8%B1%D9%8A%D8%AC%20%D8%A7%D9%84%D8%B7%D8%A8%20%D8%A7%D9%84%D8%B9%D8%A7%D9%84%D9%85%D9%8A%201')
            ->assertOk()
            ->assertSee('خريج الطب العالمي 1')
            ->assertSee('dir="rtl"', false)
            ->assertSee('دليل الخريجين')
            ->assertSee('hreflang="en"', false);
    }

    public function test_directory_is_not_published_when_no_enabled_named_record_is_available(): void
    {
        Alumni::query()->update(['is_enabled' => false]);

        Cache::flush();

        $this->get('/en/alumni')->assertNotFound();
        $this->get('/ar/alumni')->assertNotFound();
    }

    public function test_global_alumni_routes_do_not_expose_private_fields(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $this->createAlumni(
            faculty: $faculty,
            nameEn: 'Public Alumni Name',
            nameAr: 'اسم خريج عام',
            year: 2024,
            email: 'hidden-alumni@example.test',
            studentIdentifier: 'student-secret-1',
        );
        Cache::flush();

        $this->get('/en/alumni')
            ->assertOk()
            ->assertSee('Public Alumni Name')
            ->assertDontSee('hidden-alumni@example.test')
            ->assertDontSee('student-secret-1');
    }

    public function test_available_directory_is_included_in_the_localized_sitemap(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $this->createAlumni(
            faculty: $faculty,
            nameEn: 'Sitemap Alumni',
            nameAr: 'خريج خريطة الموقع',
            year: 2024,
        );

        $baseUrl = rtrim((string) config('edge.canonical_url', config('app.url')), '/');
        $locations = app(SitemapServiceInterface::class)->generateEntries()->pluck('loc')->all();

        $this->assertContains($baseUrl.'/ar/alumni', $locations);
        $this->assertContains($baseUrl.'/en/alumni', $locations);
    }

    private function departmentFor(Faculty $faculty): Department
    {
        $department = Department::query()
            ->where('faculty_id', $faculty->getKey())
            ->where('is_enabled', true)
            ->first();

        if ($department instanceof Department) {
            return $department;
        }

        $department = Department::query()->create([
            'faculty_id' => $faculty->getKey(),
            'slug' => 'verified-department',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        DepartmentTranslation::query()->create([
            'department_id' => $department->getKey(),
            'locale' => 'ar',
            'name' => 'قسم موثق',
        ]);
        DepartmentTranslation::query()->create([
            'department_id' => $department->getKey(),
            'locale' => 'en',
            'name' => 'Verified Department',
        ]);

        return $department;
    }

    private function createAlumni(
        Faculty $faculty,
        string $nameEn,
        string $nameAr,
        int $year,
        ?Department $department = null,
        ?string $email = null,
        ?string $studentIdentifier = null,
    ): Alumni {
        $alumni = Alumni::query()->create([
            'faculty_id' => $faculty->getKey(),
            'department_id' => $department?->getKey(),
            'degree' => 'Bachelor',
            'graduation_year' => $year,
            'email' => $email,
            'student_identifier' => $studentIdentifier,
            'is_enabled' => true,
        ]);
        AlumniTranslation::query()->create([
            'alumni_id' => $alumni->getKey(),
            'locale' => 'ar',
            'full_name' => $nameAr,
        ]);
        AlumniTranslation::query()->create([
            'alumni_id' => $alumni->getKey(),
            'locale' => 'en',
            'full_name' => $nameEn,
        ]);

        return $alumni;
    }
}
