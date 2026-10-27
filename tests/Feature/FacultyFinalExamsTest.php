<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Filament\Pages\ManageDentistryFaculty;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
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

    public function test_a_faculty_without_a_final_exams_page_or_card_still_gets_one(): void
    {
        // As on the live site, where only the faculties whose old site had a schedule got the rows.
        $dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        FacultyPage::query()->where('faculty_id', $dentistry->getKey())->where('slug', 'final-exams')->forceDelete();
        FacultySubpageCard::query()->where('faculty_slug', 'dentistry')->where('subpage_slug', 'final-exams')->delete();
        Cache::flush();

        $this->get('/en/faculties/dentistry')->assertOk()->assertDontSee('/en/faculties/dentistry/final-exams', false);
        $this->get('/en/faculties/dentistry/final-exams')->assertOk()->assertSee('The final exam schedule will be published here when it is issued.');

        $this->publishSchedule('dentistry', [
            'ar' => ['title' => 'الفصل الأول', 'documents' => [['label' => '', 'url' => '/downloads/files/dent-ar.pdf']], 'rows' => []],
            'en' => ['title' => 'First semester', 'documents' => [['label' => '', 'url' => '/downloads/files/dent-en.pdf']], 'rows' => []],
        ]);

        foreach (['ar', 'en'] as $locale) {
            $this->get("/{$locale}/faculties/dentistry")->assertOk()->assertSee("/{$locale}/faculties/dentistry/final-exams", false);
            $this->get("/{$locale}/faculties/dentistry/final-exams")->assertOk()->assertSee("/downloads/files/dent-{$locale}.pdf", false);
        }
    }

    public function test_editor_picks_a_schedule_file_and_publishing_shows_the_card(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');
        $target = 'facilities.dentistry.final-exams';

        $component = Livewire::test(ManageDentistryFaculty::class)
            ->set('data.target_key', $target)
            ->call('loadTarget', $target)
            ->assertSee(__('admin.faculty_workspace.editor.fields.document_file'))
            ->assertDontSee(__('admin.faculty_workspace.editor.fields.file_url'));

        foreach (['ar' => 'الفصل الأول', 'en' => 'First semester'] as $locale => $title) {
            $component
                ->set("data.{$locale}_content.summary", $title)
                ->set("data.{$locale}_content.payload.schedules", [[
                    'title' => $title,
                    'note' => '',
                    'documents' => [['label' => '', 'url' => "/storage/media/exams-{$locale}.pdf", 'urlMediaId' => null]],
                    'rows' => [],
                ]]);
        }

        $component->call('save')->assertHasNoErrors()->call('publish')->assertHasNoErrors();
        Cache::flush();

        foreach (['ar', 'en'] as $locale) {
            $this->get("/{$locale}/faculties/dentistry/overview")->assertOk()->assertSee("/{$locale}/faculties/dentistry/final-exams", false);
            $this->get("/{$locale}/faculties/dentistry/final-exams")->assertOk()->assertSee("/storage/media/exams-{$locale}.pdf", false);
        }
    }

    public function test_a_hidden_final_exams_card_stays_hidden(): void
    {
        FacultySubpageCard::query()->where('faculty_slug', 'pharmacy')->where('subpage_slug', 'final-exams')->update(['is_visible' => false]);

        $this->publishSchedule('pharmacy', [
            'ar' => null,
            'en' => ['title' => 'First semester', 'documents' => [['label' => '', 'url' => '/downloads/files/pharm.pdf']], 'rows' => []],
        ]);

        $this->get('/en/faculties/pharmacy')->assertOk()->assertDontSee('/en/faculties/pharmacy/final-exams', false);
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
            $payload['translations'][$locale]['summary'] = $locale === 'ar' ? 'برنامج الامتحانات النهائية.' : 'The final examination schedule.';
        }

        $workflow->saveDraft($targetKey, $payload, (int) $author->getKey());
        self::assertTrue($workflow->publish($targetKey, (int) $author->getKey()));
        Cache::flush();
    }
}
