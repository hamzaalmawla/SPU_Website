<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Media\MediaDisplaySettingsServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Models\Career\HonorStudent;
use App\Models\Career\HonorStudentTranslation;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
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

    public function test_a_framed_library_photo_replaces_the_old_site_photo_on_the_students_other_records(): void
    {
        // The old site gave one semester a photo; an editor later chose a library
        // photo for another semester and framed it.
        $framed = $this->libraryPhoto('framed-student', focalX: 53.3, focalY: 31.7);
        $this->honor('medicine', 'اليانا قرياقوس قورياكس', '2023 / 1', 'old-site-photo-test.jpg');
        $this->honor('medicine', 'اليانا قرياقوس قورياكس', '2023 / 2', mediaId: (int) $framed->getKey());
        // A student with two library photos keeps each record's own.
        $first = $this->libraryPhoto('first-own');
        $second = $this->libraryPhoto('second-own');
        $this->honor('medicine', 'لينا محمود ديب', '2022 / 2', mediaId: (int) $first->getKey());
        $this->honor('medicine', 'لينا محمود ديب', '2023 / 1', 'old-site-lina-test.jpg');
        $this->honor('medicine', 'لينا محمود ديب', '2023 / 2', mediaId: (int) $second->getKey());
        Cache::flush();

        foreach (['ar', 'en'] as $locale) {
            $items = collect(app(FacultyPageServiceInterface::class)->getSubpage('medicine', 'valedictorians', $locale)?->items ?? []);

            $images = $items->filter(fn (array $item): bool => str_contains((string) $item['title'], 'قرياقوس'))->pluck('image');
            $this->assertCount(2, $images, $locale);
            $this->assertSame(['/storage/media/image/framed-student.webp'], $images->unique()->values()->all(), $locale);

            $lina = $items->filter(fn (array $item): bool => str_contains((string) $item['title'], 'لينا'))->pluck('image')->map(fn (mixed $image): string => basename((string) $image))->all();
            $this->assertCount(3, $lina);
            $this->assertNotContains('old-site-lina-test.jpg', $lina);
            $this->assertContains('first-own.webp', $lina);
            $this->assertContains('second-own.webp', $lina);
        }

        // The framing rule matches the URL every one of the student's cards now uses.
        $stylesheet = app(MediaDisplaySettingsServiceInterface::class)->stylesheet();
        $this->assertStringContainsString('img[src*="/storage/media/image/framed-student.webp"]', $stylesheet);
        $this->assertStringContainsString('object-position:53.3% 31.7%', $stylesheet);
    }

    private function libraryPhoto(string $name, float $focalX = 50, float $focalY = 50): MediaAsset
    {
        return MediaAsset::query()->create([
            'disk' => 'public',
            'directory' => 'media/image',
            'filename' => $name.'.jpg',
            'original_name' => $name.'.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'media_type' => 'image',
            'library_scope' => 'main',
            'metadata_status' => 'reviewed',
            'path' => 'media/image/'.$name.'.jpg',
            'webp_path' => 'media/image/'.$name.'.webp',
            'focal_x' => $focalX,
            'focal_y' => $focalY,
            'display_fit' => 'cover',
        ]);
    }

    private function honor(string $facultySlug, string $name, string $academicYear, ?string $photo = null, ?int $mediaId = null): void
    {
        $faculty = Faculty::query()->where('public_slug', $facultySlug)->firstOrFail();
        $student = HonorStudent::query()->create([
            'faculty_id' => (int) $faculty->getKey(),
            'academic_year' => $academicYear,
            'legacy_photo_path' => $photo,
            'photo_media_id' => $mediaId,
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
