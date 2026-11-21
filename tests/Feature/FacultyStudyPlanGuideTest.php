<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Filament\Pages\ManageDentistryFaculty;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The student help on a faculty study plan: the "How to read this plan" guide and
 * the course types with what each means, from the defaults or the editor's wording.
 */
final class FacultyStudyPlanGuideTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'facilities.dentistry.study_plan';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_study_plan_shows_the_guide_and_explains_each_course_type_in_both_languages(): void
    {
        foreach ([
            'en' => ['How to read this study plan', 'Lines show what comes first', 'Taken by every student of this faculty.'],
            'ar' => ['كيف تقرأ الخطة الدراسية', 'الخطوط تبيّن ما يجب دراسته أولاً', 'يدرسه جميع طلاب هذه الكلية.'],
        ] as $locale => $texts) {
            $html = (string) $this->get('/'.$locale.'/faculties/dentistry/study-plan')->assertOk()->getContent();

            $this->assertStringContainsString('data-study-plan-guide', $html);
            foreach ($texts as $text) {
                $this->assertStringContainsString(e($text), $html);
            }
            // The type cards still filter the plan.
            $this->assertStringContainsString('data-legend-type="faculty" aria-pressed="false"', $html);
        }
    }

    public function test_the_editor_starts_from_the_defaults_and_their_wording_replaces_them(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $this->actingAs($editor, 'web');

        $page = Livewire::withQueryParams(['target' => self::TARGET])->test(ManageDentistryFaculty::class)
            ->assertSet('data.en_content.payload.guide.title', 'How to read this study plan')
            ->assertSet('data.ar_content.payload.guide.title', 'كيف تقرأ الخطة الدراسية');

        $steps = $page->get('data.en_content.payload.guide.steps');
        $this->assertCount(6, $steps);
        $legend = collect($page->get('data.en_content.payload.legend'))->keyBy('id');
        $this->assertSame('Taken by every student of this faculty.', $legend['faculty']['description']);

        $firstStep = array_key_first($steps);
        $firstType = collect($page->get('data.en_content.payload.legend'))->search(fn (array $item): bool => $item['id'] === 'faculty');
        $page->set('data.en_content.payload.guide.title', 'Reading your plan')
            ->set('data.en_content.payload.guide.steps.'.$firstStep.'.title', 'One card, one course')
            ->set('data.en_content.payload.legend.'.$firstType.'.description', 'Every dentistry student takes it.')
            ->call('save')
            ->assertHasNoErrors();

        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->publish(self::TARGET, (int) $editor->getKey());
        $this->flushPublicCache();

        $html = (string) $this->get('/en/faculties/dentistry/study-plan')->assertOk()->getContent();
        $this->assertStringContainsString('Reading your plan', $html);
        $this->assertStringContainsString('One card, one course', $html);
        $this->assertStringContainsString('Every dentistry student takes it.', $html);
        $this->assertStringNotContainsString('How to read this study plan', $html);

        $saved = app(FacultyPageServiceInterface::class)->getEditablePayload(self::TARGET);
        $this->assertSame('كيف تقرأ الخطة الدراسية', $saved['translations']['ar']['payload']['guide']['title']);
    }

    private function flushPublicCache(): void
    {
        auth()->logout();
        cache()->flush();
    }
}
