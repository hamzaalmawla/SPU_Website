<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Enums\FormSubmissionInbox;
use App\Filament\Resources\FormDefinitionResource\Pages\CreateFormDefinition;
use App\Filament\Resources\FormDefinitionResource\Pages\EditFormDefinition;
use App\Filament\Resources\FormDefinitionResource\Pages\ListFormDefinitions;
use App\Models\Form\DynamicFormSubmission;
use App\Models\Form\FormDefinition;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class FormBuilderAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $this->actingAs($this->editor, 'web');
    }

    public function test_the_built_in_forms_are_listed(): void
    {
        $forms = FormDefinition::query()->orderBy('id')->get();

        $this->assertSame(
            ['conference-registration', 'symposium-registration', 'activity-registration', 'admissions-application', 'job-application', 'suggestions-complaints'],
            $forms->pluck('key')->all(),
        );

        Livewire::test(ListFormDefinitions::class)->assertCanSeeTableRecords($forms);
    }

    public function test_an_editor_builds_a_form_with_a_dropdown_and_a_file(): void
    {
        $undo = Repeater::fake();

        Livewire::test(CreateFormDefinition::class)
            ->fillForm([
                'nameAr' => 'التسجيل في اليوم المفتوح',
                'nameEn' => 'Open day registration',
                'key' => 'open-day',
                'inbox' => FormSubmissionInbox::EVENT_REGISTRATIONS->value,
                'isEnabled' => true,
                'multiStep' => false,
                'steps' => [[
                    'fields' => [
                        ['labelAr' => 'الاسم', 'labelEn' => 'Name', 'name' => 'full_name', 'type' => 'text', 'required' => true],
                        ['labelAr' => 'السنة', 'labelEn' => 'Year', 'name' => 'year', 'type' => 'select', 'required' => true, 'options' => [
                            ['value' => 'first', 'labelAr' => 'الأولى', 'labelEn' => 'First'],
                            ['value' => 'second', 'labelAr' => 'الثانية', 'labelEn' => 'Second'],
                        ]],
                        ['labelAr' => 'الشهادة', 'labelEn' => 'Certificate', 'name' => 'certificate', 'type' => 'file', 'required' => false, 'fileTypes' => ['pdf', 'jpg'], 'maxMegabytes' => 2],
                    ],
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undo();

        $form = app(FormDefinitionServiceInterface::class)->find('open-day');
        $this->assertNotNull($form);
        $this->assertSame(FormSubmissionInbox::EVENT_REGISTRATIONS, $form->inbox);
        $this->assertSame(['full_name', 'year', 'certificate'], array_column($form->fields, 'name'));
        $this->assertSame(['first', 'second'], array_column($form->fields[1]['options'], 'value'));
        $this->assertSame('.pdf,.jpg', $form->fields[2]['accept']);
        $this->assertSame(2048, $form->fields[2]['maxKilobytes']);
        $this->assertSame('Open day registration', app(FormDefinitionServiceInterface::class)->options('en')['open-day']);
    }

    public function test_a_form_with_a_repeated_field_id_is_not_saved(): void
    {
        $undo = Repeater::fake();

        Livewire::test(CreateFormDefinition::class)
            ->fillForm([
                'nameAr' => 'نموذج',
                'nameEn' => 'Form',
                'key' => 'broken-form',
                'inbox' => FormSubmissionInbox::SUGGESTIONS->value,
                'steps' => [[
                    'fields' => [
                        ['labelAr' => 'أ', 'labelEn' => 'A', 'name' => 'same', 'type' => 'text'],
                        ['labelAr' => 'ب', 'labelEn' => 'B', 'name' => 'same', 'type' => 'text'],
                    ],
                ]],
            ])
            ->call('create')
            ->assertNotified(__('admin.forms.notifications.not_saved'));

        $undo();

        $this->assertNull(app(FormDefinitionServiceInterface::class)->find('broken-form'));
    }

    public function test_editing_keeps_the_steps_and_the_id(): void
    {
        $record = FormDefinition::query()->where('key', 'job-application')->firstOrFail();

        Livewire::test(EditFormDefinition::class, ['record' => $record->getKey()])
            ->assertFormSet(['key' => 'job-application', 'multiStep' => true, 'nameEn' => $record->name_en])
            ->fillForm(['nameEn' => 'Apply for a job'])
            ->call('save')
            ->assertHasNoFormErrors();

        $form = app(FormDefinitionServiceInterface::class)->find('job-application');
        $this->assertSame('Apply for a job', $form?->nameEn);
        $this->assertCount(5, $form->steps);
        $this->assertCount(24, $form->fields);
    }

    public function test_a_form_in_use_cannot_be_deleted_but_can_be_disabled(): void
    {
        $record = FormDefinition::query()->where('key', 'suggestions-complaints')->firstOrFail();

        Livewire::test(ListFormDefinitions::class)
            ->callTableAction('delete', $record)
            ->assertNotified(__('admin.forms.notifications.cannot_delete'));
        $this->assertTrue(FormDefinition::query()->whereKey($record->getKey())->exists());

        Livewire::test(ListFormDefinitions::class)->callTableAction('toggle', $record);
        $this->assertFalse((bool) $record->refresh()->is_enabled);
        $this->assertArrayNotHasKey('suggestions-complaints', app(FormDefinitionServiceInterface::class)->options('ar'));
        $this->assertNull(app(FormDefinitionServiceInterface::class)->publicSchema('suggestions-complaints'));
    }

    public function test_an_unused_form_is_deleted_and_one_with_submissions_is_kept(): void
    {
        $service = app(FormDefinitionServiceInterface::class);
        $data = ['key' => 'unused', 'nameAr' => 'غير مستخدم', 'nameEn' => 'Unused', 'inbox' => 'suggestions', 'steps' => [['fields' => [['labelAr' => 'أ', 'labelEn' => 'A', 'name' => 'a', 'type' => 'text']]]]];
        $unused = $service->save(null, $data, (int) $this->editor->getKey());
        $used = $service->save(null, ['key' => 'used', 'nameEn' => 'Used'] + $data, (int) $this->editor->getKey());
        DynamicFormSubmission::query()->create(['reference_number' => 'SPU-TEST-1', 'form_id' => 'used', 'locale' => 'en', 'payload_json' => ['a' => 'x'], 'status' => 'new']);

        Livewire::test(ListFormDefinitions::class)
            ->callTableAction('delete', $unused->id)
            ->assertNotified(__('admin.forms.notifications.deleted'))
            ->callTableAction('delete', $used->id)
            ->assertNotified(__('admin.forms.notifications.cannot_delete'));

        $this->assertNull($service->find('unused'));
        $this->assertNotNull($service->find('used'));
    }

    public function test_faculty_editors_cannot_open_the_forms_page(): void
    {
        $this->actingAs(User::factory()->create(['role_slug' => 'faculty_editor', 'is_locked' => false]), 'web');

        $this->get('/admin/form-definitions')->assertForbidden();
    }
}
