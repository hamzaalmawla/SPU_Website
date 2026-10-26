<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FacultyFinalExamsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    #[DataProvider('facultyProvider')]
    public function test_every_faculty_shows_its_final_exams_once_a_schedule_file_is_published(string $facultySlug): void
    {
        foreach (['ar', 'en'] as $locale) {
            $this->get("/{$locale}/faculties/{$facultySlug}")->assertOk()->assertDontSee("/{$locale}/faculties/{$facultySlug}/final-exams", false);
        }

        $this->publishSchedule($facultySlug, [
            'ar' => ['title' => 'الفصل الأول 2026/2027', 'documents' => [['label' => 'برنامج الامتحان', 'url' => '/downloads/files/exams-ar.pdf']], 'rows' => []],
            'en' => ['title' => 'First semester 2026/2027', 'documents' => [['label' => 'Exam schedule', 'url' => '/downloads/files/exams-en.pdf']], 'rows' => []],
        ]);

        foreach (['ar', 'en'] as $locale) {
            $this->get("/{$locale}/faculties/{$facultySlug}")->assertOk()->assertSee("/{$locale}/faculties/{$facultySlug}/final-exams", false);
            $this->get("/{$locale}/faculties/{$facultySlug}/overview")->assertOk()->assertSee("/{$locale}/faculties/{$facultySlug}/final-exams", false);
            $this->get("/{$locale}/faculties/{$facultySlug}/final-exams")->assertOk()->assertSee("/downloads/files/exams-{$locale}.pdf", false);
        }
    }

    public function test_a_published_page_without_a_schedule_file_stays_off_the_overview(): void
    {
        $this->publishSchedule('dentistry', [
            'ar' => null,
            'en' => ['title' => 'First semester 2026/2027', 'documents' => [], 'rows' => []],
        ]);

        $this->get('/en/faculties/dentistry')->assertOk()->assertDontSee('/en/faculties/dentistry/final-exams', false);
        $this->get('/ar/faculties/dentistry')->assertOk()->assertDontSee('/ar/faculties/dentistry/final-exams', false);
        $this->get('/en/faculties/dentistry/final-exams')->assertOk()->assertSee('First semester 2026/2027');
        $this->get('/ar/faculties/dentistry/final-exams')
            ->assertOk()
            ->assertSee('سيُنشر برنامج الامتحان النهائي هنا عند صدوره.');
    }

    public function test_a_timetable_without_a_file_counts_as_a_schedule(): void
    {
        $this->publishSchedule('pharmacy', [
            'ar' => null,
            'en' => ['title' => 'First semester', 'documents' => [], 'rows' => [['day' => 'Sunday', 'date' => '2027-01-10', 'time' => '10:00', 'course' => 'Pharmacology', 'year' => '3', 'room' => 'A1']]],
        ]);

        $this->get('/en/faculties/pharmacy')->assertOk()->assertSee('/en/faculties/pharmacy/final-exams', false);
        $this->get('/ar/faculties/pharmacy')->assertOk()->assertDontSee('/ar/faculties/pharmacy/final-exams', false);
    }

    public function test_migration_adds_the_page_and_card_without_reviving_a_hidden_card(): void
    {
        $dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        FacultyPage::query()->where('faculty_id', $dentistry->getKey())->where('slug', 'final-exams')->forceDelete();
        FacultySubpageCard::query()->where('faculty_slug', 'dentistry')->where('subpage_slug', 'final-exams')->delete();
        FacultySubpageCard::query()->where('faculty_slug', 'pharmacy')->where('subpage_slug', 'final-exams')->update(['is_visible' => false]);

        $migration = require database_path('migrations/2026_10_27_000001_add_final_exams_subpage_to_all_faculties.php');
        $migration->up();

        $page = FacultyPage::query()->where('faculty_id', $dentistry->getKey())->where('slug', 'final-exams')->firstOrFail();
        self::assertTrue((bool) $page->is_enabled);
        self::assertSame(['ar', 'en'], $page->translations()->orderBy('locale')->pluck('locale')->all());
        self::assertTrue(FacultySubpageCard::query()->where('faculty_slug', 'dentistry')->where('subpage_slug', 'final-exams')->where('is_visible', true)->exists());
        self::assertFalse((bool) FacultySubpageCard::query()->where('faculty_slug', 'pharmacy')->where('subpage_slug', 'final-exams')->value('is_visible'));
        self::assertSame(1, FacultySubpageCard::query()->where('faculty_slug', 'medicine')->where('subpage_slug', 'final-exams')->count());
    }

    /** @return iterable<string, array{string}> */
    public static function facultyProvider(): iterable
    {
        foreach (['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'] as $slug) {
            yield $slug => [$slug];
        }
    }

    /** @param array<string, array<string, mixed>|null> $schedules */
    private function publishSchedule(string $facultySlug, array $schedules): void
    {
        $targetKey = "facilities.{$facultySlug}.final-exams";
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload($targetKey);

        foreach ($schedules as $locale => $schedule) {
            $payload['translations'][$locale]['payload']['schedules'] = $schedule === null ? [] : [$schedule];
        }

        $workflow->saveDraft($targetKey, $payload, (int) $author->getKey());
        self::assertTrue($workflow->publish($targetKey, (int) $author->getKey()));
        Cache::flush();
    }
}
