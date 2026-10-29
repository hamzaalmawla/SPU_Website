<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyMasterProgramServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\DTOs\Seo\SitemapEntryDTO;
use App\Filament\Pages\ManageArtificialIntelligenceFaculty;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class FacultyMasterProgramTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/faculties/artificial-intelligence/master-program';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_ai_master_program_is_live_with_the_shipped_curriculum_in_both_languages(): void
    {
        $this->get('/en'.self::URL)
            ->assertOk()
            ->assertSee("Professional Master's in Artificial Intelligence & Data Sciences")
            ->assertSee('data-master-course="MADS101"', false)
            ->assertSee('data-master-course="MADS405"', false)
            ->assertSee('data-prerequisites="MADS101 MADS103"', false)
            ->assertSee('Apply advanced mathematical, statistical, and computational foundations')
            ->assertSee('Informatics Engineering')
            ->assertSee('To register for the Master&#039;s project, the student must have completed at least 72 credit hours.', false)
            ->assertSee('<link rel="alternate" hreflang="ar"', false);

        $this->get('/ar'.self::URL)
            ->assertOk()
            ->assertSee('ماجستير تأهيل وتخصص في الذكاء الاصطناعي وعلوم البيانات')
            ->assertSee('البرمجة المتقدمة بلغة بايثون للذكاء الاصطناعي')
            ->assertSee('كتابة نصوص Python متقدمة وتصحيحها')
            ->assertSee('الهندسة المعلوماتية')
            ->assertSee('dir="rtl"', false);
    }

    public function test_the_program_totals_come_from_the_courses(): void
    {
        $program = app(FacultyMasterProgramServiceInterface::class)->program(
            (array) app(FacultyMasterProgramServiceInterface::class)->defaultContent('artificial-intelligence', 'en')['payload'],
        );

        // The source's totals row says 72 theory / 52 practical; its courses add up to 73 / 54.
        $this->assertSame(['courses' => 23, 'credits' => 100, 'theory' => 73, 'practical' => 54], $program['totals']);
        $this->assertSame([24, 24, 24, 28], array_column($program['terms'], 'credits'));

        $machineLearning = collect($program['terms'][1]['courses'])->firstWhere('code', 'MADS201');
        $this->assertSame(['MADS101', 'MADS103'], array_column($machineLearning['prerequisites'], 'code'));
        $this->assertSame([], $machineLearning['unlocks']);

        $deepLearning = collect($program['terms'][1]['courses'])->firstWhere('code', 'MADS202');
        $this->assertSame(['MADS301', 'MADS302', 'MADS303', 'MADS304', 'MADS306'], array_column($deepLearning['unlocks'], 'code'));
    }

    public function test_the_highlights_card_shows_on_the_ai_faculty_only(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $this->assertSame("/{$locale}".self::URL, $this->firstCardLink("/{$locale}/faculties/artificial-intelligence", 'highlights'));
            $this->assertSame("/{$locale}".self::URL, $this->firstCardLink("/{$locale}/faculties/artificial-intelligence/overview", 'faculty-pathways'));
            $this->get("/{$locale}/faculties/medicine")->assertOk()->assertDontSee('master-program', false);
            $this->get("/{$locale}/faculties/medicine/master-program")->assertNotFound();
        }
    }

    public function test_a_hidden_card_or_disabled_page_keeps_it_off(): void
    {
        FacultySubpageCard::query()->create([
            'faculty_slug' => 'artificial-intelligence',
            'subpage_slug' => 'master-program',
            'sort_order' => 1,
            'is_visible' => false,
            'status' => 'published',
            'published_at' => now(),
        ]);
        Cache::flush();

        $this->get('/en/faculties/artificial-intelligence')->assertOk()->assertDontSee('/en'.self::URL, false);
        $this->get('/en'.self::URL)->assertNotFound();

        FacultySubpageCard::query()->where('subpage_slug', 'master-program')->delete();
        $faculty = Faculty::query()->where('public_slug', 'artificial-intelligence')->firstOrFail();
        FacultyPage::query()->create([
            'faculty_id' => $faculty->getKey(),
            'slug' => 'master-program',
            'kind' => 'master-program',
            'sort_order' => 99,
            'is_enabled' => false,
        ]);
        Cache::flush();

        $this->get('/en/faculties/artificial-intelligence')->assertOk()->assertDontSee('/en'.self::URL, false);
        $this->get('/en'.self::URL)->assertNotFound();
    }

    public function test_a_visible_card_places_the_page_without_a_page_row(): void
    {
        FacultySubpageCard::query()->create([
            'faculty_slug' => 'artificial-intelligence',
            'subpage_slug' => 'master-program',
            'title_override_en' => 'MSc in AI',
            'sort_order' => 0,
            'is_visible' => true,
            'status' => 'published',
            'published_at' => now(),
        ]);
        Cache::flush();

        $this->get('/en/faculties/artificial-intelligence')->assertOk()->assertSee('MSc in AI')->assertSee('/en'.self::URL, false);
    }

    public function test_a_published_version_replaces_the_shipped_curriculum_and_a_draft_never_shows(): void
    {
        $targetKey = 'facilities.artificial-intelligence.master-program';
        $author = (int) User::query()->where('role_slug', 'super_admin')->firstOrFail()->getKey();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload($targetKey);

        $this->assertSame('MADS101', $payload['translations']['en']['payload']['terms'][0]['courses'][0]['code']);

        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['payload']['terms'][0]['courses'][0]['title'] = "Python for AI ({$locale})";
        }

        $workflow->saveDraft($targetKey, $payload, $author);
        Cache::flush();
        $this->get('/en'.self::URL)->assertOk()->assertDontSee('Python for AI (en)');

        $this->assertTrue($workflow->publish($targetKey, $author));
        Cache::flush();

        $this->get('/en'.self::URL)->assertOk()->assertSee('Python for AI (en)')->assertDontSee('Advanced Python Programming for AI</span>', false);
        $this->get('/ar'.self::URL)->assertOk()->assertSee('Python for AI (ar)');
    }

    public function test_publishing_rejects_an_unknown_prerequisite_or_mismatched_courses(): void
    {
        $targetKey = 'facilities.artificial-intelligence.master-program';
        $author = (int) User::query()->where('role_slug', 'super_admin')->firstOrFail()->getKey();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload($targetKey);
        $payload['translations']['en']['payload']['terms'][1]['courses'][0]['prerequisites'] = ['MADS999'];
        unset($payload['translations']['ar']['payload']['terms'][3]['courses'][4]);

        $errors = app(FacultyMasterProgramServiceInterface::class)->validationErrors($targetKey, $payload);
        $this->assertContains('MADS201 lists MADS999 as a prerequisite, which is not another course of the program.', $errors['en']);
        $this->assertContains('The Arabic and English pages must list the same course codes.', $errors['en']);

        $workflow->saveDraft($targetKey, $payload, $author);
        $this->expectException(ValidationException::class);
        $workflow->publish($targetKey, $author);
    }

    public function test_editor_changes_a_course_and_publishes(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');
        $target = 'facilities.artificial-intelligence.master-program';

        $component = Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', $target)
            ->call('loadTarget', $target)
            ->assertSee(__('admin.faculty_workspace.editor.sections.master_terms'))
            ->assertSee('MADS101');

        foreach (['ar', 'en'] as $locale) {
            $terms = $component->get("data.{$locale}_content.payload.terms");
            $termKey = array_key_first($terms);
            $courseKey = array_key_first($terms[$termKey]['courses']);
            $component->set("data.{$locale}_content.payload.terms.{$termKey}.courses.{$courseKey}.description", "Edited description ({$locale})");
        }

        $component->call('save')->assertHasNoErrors()->call('publish')->assertHasNoErrors();
        Cache::flush();

        foreach (['ar', 'en'] as $locale) {
            $this->get("/{$locale}".self::URL)
                ->assertOk()
                ->assertSee("Edited description ({$locale})")
                ->assertSee('data-master-course="MADS405"', false);
        }
    }

    public function test_the_sitemap_lists_both_languages(): void
    {
        $locs = app(SitemapServiceInterface::class)->generateSectionEntries('faculties')
            ->map(fn (SitemapEntryDTO $entry): string => $entry->loc)
            ->all();

        $this->assertNotEmpty(array_filter($locs, fn (string $loc): bool => str_ends_with($loc, '/en'.self::URL)));
        $this->assertNotEmpty(array_filter($locs, fn (string $loc): bool => str_ends_with($loc, '/ar'.self::URL)));
    }

    private function firstCardLink(string $url, string $sectionId): ?string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$this->get($url)->assertOk()->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $link = (new DOMXPath($document))->query("//section[@id='{$sectionId}']//a[h3]")?->item(0);

        return $link instanceof DOMElement ? $link->getAttribute('href') : null;
    }
}
