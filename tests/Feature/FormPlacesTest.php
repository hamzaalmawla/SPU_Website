<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Filament\Pages\ManageAdmissions;
use App\Filament\Support\FormPicker;
use App\Models\Cms\CmsDraft;
use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every place that takes a form shows the one its editor picked from Forms.
 */
final class FormPlacesTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private CmsWorkflowServiceInterface $workflow;

    private FormDefinitionServiceInterface $forms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $this->workflow = app(CmsWorkflowServiceInterface::class);
        $this->forms = app(FormDefinitionServiceInterface::class);

        $this->forms->save(null, [
            'key' => 'open-day',
            'nameAr' => 'التسجيل في اليوم المفتوح',
            'nameEn' => 'Open day registration',
            'inbox' => 'event_registrations',
            'steps' => [['fields' => [
                ['labelAr' => 'الاسم', 'labelEn' => 'Your name', 'name' => 'visitorName', 'type' => 'text', 'required' => true],
                ['labelAr' => 'البريد', 'labelEn' => 'Email', 'name' => 'email', 'type' => 'email', 'required' => true],
            ]]],
        ], (int) $this->author->getKey());
    }

    public function test_the_dropdown_lists_enabled_forms_by_name_and_keeps_a_disabled_pick(): void
    {
        app()->setLocale('en');

        $this->assertSame('Open day registration', FormPicker::options()['open-day']);
        $this->assertSame('Suggestions and complaints', FormPicker::options()['suggestions-complaints']);

        $this->forms->setEnabled($this->forms->find('open-day')->id, false, (int) $this->author->getKey());

        $this->assertArrayNotHasKey('open-day', FormPicker::options());
        $this->assertSame('Open day registration (disabled)', FormPicker::options('open-day')['open-day']);
    }

    public function test_a_research_event_shows_the_picked_form_until_it_is_disabled(): void
    {
        $this->publishConferencesWithForm('conf-001', 'open-day');

        $this->get('/en/research/conferences/register?event=conf-001')
            ->assertOk()
            ->assertSee('data-form-id="open-day"', false)
            ->assertSee('"name":"visitorName"', false);

        $this->postJson('/en/forms/open-day/submissions', [
            'visitorName' => 'Visitor',
            'email' => 'visitor@example.com',
            'event_source' => 'research-conferences',
            'event_id' => 'conf-001',
        ])->assertCreated();
        $this->assertSame('conf-001', DynamicFormSubmission::query()->where('form_id', 'open-day')->firstOrFail()->payload_json['_context']['event_id'] ?? null);

        $this->forms->setEnabled($this->forms->find('open-day')->id, false, (int) $this->author->getKey());

        $this->get('/en/research/conferences/register?event=conf-001')->assertOk()->assertSee('Event Not Found');
        $this->postJson('/en/forms/open-day/submissions', ['visitorName' => 'Late', 'email' => 'late@example.com'])->assertUnprocessable();
    }

    public function test_the_job_application_uses_the_form_picked_on_the_job_board(): void
    {
        $payload = app(CampusLifePageServiceInterface::class)->getEditablePayload('campus_life.jobs');
        $payload['translations']['ar']['applicationFormId'] = 'open-day';
        $payload['translations']['en']['applicationFormId'] = 'open-day';
        $this->publish('campus_life.jobs', $payload);

        $this->get('/en/campus-life/career-development/jobs/apply?job=lecturer-computer-science')
            ->assertOk()
            ->assertSee('data-form-id="open-day"', false);
    }

    public function test_the_admissions_editor_picks_the_application_form_and_the_page_shows_it(): void
    {
        $this->actingAs($this->author, 'web');

        Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.how-to-apply')
            ->call('loadTarget', 'admissions.how-to-apply')
            ->assertSet('data.application_form_id', 'admissions-application')
            ->set('data.application_form_id', 'open-day')
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'admissions.how-to-apply')->latest('id')->firstOrFail();
        $this->assertSame('open-day', $draft->payload_json['translations']['ar']['applicationFormId'] ?? null);
        $this->assertSame('open-day', $draft->payload_json['translations']['en']['applicationFormId'] ?? null);

        $payload = app(AdmissionsPageServiceInterface::class)->getEditablePayload('admissions.how-to-apply');
        $payload['translations']['ar']['applicationFormId'] = 'open-day';
        $payload['translations']['en']['applicationFormId'] = 'open-day';
        $this->publish('admissions.how-to-apply', $payload);

        $this->get('/en/admissions/how-to-apply')->assertOk()->assertSee('data-form-id="open-day"', false)->assertSee('"name":"visitorName"', false);
    }

    public function test_a_form_picked_by_a_page_cannot_be_deleted(): void
    {
        $this->publishConferencesWithForm('conf-001', 'open-day');

        try {
            $this->forms->delete($this->forms->find('open-day')->id, (int) $this->author->getKey());
            $this->fail('A form in use was deleted.');
        } catch (ValidationException $exception) {
            // The message names the page that uses it.
            $this->assertStringContainsString('المؤتمرات', $exception->errors()['form'][0]);
        }

        $this->assertNotNull($this->forms->find('open-day'));
    }

    public function test_the_built_in_application_forms_count_as_in_use_while_no_page_picks_another(): void
    {
        foreach (['admissions-application', 'job-application', 'suggestions-complaints'] as $key) {
            $this->assertNotSame([], $this->forms->usage($key, 'en'), $key);
        }
    }

    public function test_a_label_cannot_break_out_of_the_printed_form(): void
    {
        $this->forms->save($this->forms->find('open-day')->id, [
            'nameAr' => 'التسجيل في اليوم المفتوح',
            'nameEn' => 'Open day registration',
            'inbox' => 'event_registrations',
            'steps' => [['fields' => [
                ['labelAr' => 'الاسم', 'labelEn' => '</script><script>alert(1)</script>', 'name' => 'visitorName', 'type' => 'text'],
            ]]],
        ], (int) $this->author->getKey());
        $this->publishConferencesWithForm('conf-001', 'open-day');

        $this->get('/en/research/conferences/register?event=conf-001')
            ->assertOk()
            ->assertSee('data-form-id="open-day"', false)
            ->assertDontSee('<script>alert(1)', false);
    }

    private function publishConferencesWithForm(string $eventId, string $formId): void
    {
        $payload = app(ResearchPageServiceInterface::class)->getEditablePayload('research.conferences');

        foreach (['ar', 'en'] as $locale) {
            foreach ($payload['translations'][$locale]['past'] as &$pastEvent) {
                $pastEvent['hasProceedings'] = false;
                unset($pastEvent['proceedingsUrl']);
            }
            unset($pastEvent);

            foreach ($payload['translations'][$locale]['upcoming'] as &$event) {
                if (($event['id'] ?? null) === $eventId) {
                    $event['formId'] = $formId;
                }
            }
            unset($event);
        }

        $this->publish('research.conferences', $payload);
    }

    /** @param array<string, mixed> $payload */
    private function publish(string $targetKey, array $payload): void
    {
        $this->workflow->saveDraft($targetKey, $payload, (int) $this->author->getKey());
        $this->assertTrue($this->workflow->publish($targetKey, (int) $this->author->getKey()), $targetKey.': '.json_encode($this->workflow->readiness($targetKey, $payload)->errors));
    }
}
