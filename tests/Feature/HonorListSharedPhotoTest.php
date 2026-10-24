<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Page\FacultyPageServiceInterface;
use App\Models\Career\HonorStudent;
use App\Models\Career\HonorStudentTranslation;
use App\Models\Faculty\Faculty;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class HonorListSharedPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_a_student_listed_in_several_semesters_reuses_the_photo_from_another_record(): void
    {
        $this->honor('medicine', 'ريم أحمد الخطيب', '2021 / 1', 'shared-photo-test.jpg');
        $this->honor('medicine', 'ريم احمد الخطيب', '2021 / 2');
        $this->honor('medicine', 'ريم أحمد الخطيب', '2022 / 1');
        $this->honor('medicine', 'سارة علي حسن', '2022 / 1');
        $this->honor('medicine', 'لينا محمود ديب', '2022 / 2', 'own-photo-test.jpg');
        $this->honor('medicine', 'لينا محمود ديب', '2023 / 1', 'second-own-photo-test.jpg');
        // Same name in another faculty is another student.
        $this->honor('pharmacy', 'سارة علي حسن', '2022 / 1', 'pharmacy-photo-test.jpg');
        Cache::flush();

        foreach (['ar', 'en'] as $locale) {
            $items = collect(app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'valedictorians', $locale)?->items ?? []);

            $reem = $items->filter(fn (array $item): bool => str_contains((string) $item['title'], 'الخطيب'));
            $this->assertCount(3, $reem);
            $this->assertTrue($reem->every(fn (array $item): bool => str_contains((string) $item['image'], 'shared-photo-test.jpg')), $locale);

            $sara = $items->first(fn (array $item): bool => str_contains((string) $item['title'], 'سارة'));
            $this->assertEmpty($sara['image'] ?? null);

            $lina = $items->filter(fn (array $item): bool => str_contains((string) $item['title'], 'لينا'))->pluck('image')->map(fn (mixed $image): string => basename((string) $image))->sort()->values()->all();
            $this->assertSame(['own-photo-test.jpg', 'second-own-photo-test.jpg'], $lina);
        }
    }

    private function honor(string $facultySlug, string $name, string $academicYear, ?string $photo = null): void
    {
        $faculty = Faculty::query()->where('public_slug', $facultySlug)->firstOrFail();
        $student = HonorStudent::query()->create([
            'faculty_id' => (int) $faculty->getKey(),
            'academic_year' => $academicYear,
            'legacy_photo_path' => $photo,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        HonorStudentTranslation::query()->create([
            'honor_student_id' => (int) $student->getKey(),
            'locale' => 'ar',
            'full_name' => $name,
        ]);
    }
}
