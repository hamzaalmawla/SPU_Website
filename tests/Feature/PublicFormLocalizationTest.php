<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Arabic is the default and fallback locale, so a public form that fails
 * validation must answer in Arabic, never with an English sentence or a raw
 * "validation.required" key.
 */
final class PublicFormLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_arabic_dictionary_covers_every_framework_validation_message(): void
    {
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $arabic = require lang_path('ar/validation.php');

        $this->assertSame([], array_diff($this->messageKeys($english), $this->messageKeys($arabic)));
        $this->assertSame([], array_diff($this->messageKeys($arabic), $this->messageKeys($english)));
    }

    public function test_arabic_validation_messages_are_translated(): void
    {
        app()->setLocale('ar');

        $this->assertSame('حقل البريد الإلكتروني مطلوب.', __('validation.required', ['attribute' => __('validation.attributes.email')]));
        $this->assertSame('يجب ألا يتجاوز طول حقل الاسم 120 حرفاً.', __('validation.max.string', ['attribute' => 'الاسم', 'max' => 120]));
    }

    public function test_contact_form_reports_errors_in_arabic(): void
    {
        $this->from('/ar/contact')->post('/ar/contact', [])
            ->assertRedirect('/ar/contact')
            ->assertSessionHasErrors([
                'name' => 'حقل الاسم مطلوب.',
                'email' => 'حقل البريد الإلكتروني مطلوب.',
                'subject' => 'حقل الموضوع مطلوب.',
                'message' => 'حقل الرسالة مطلوب.',
            ]);
    }

    public function test_contact_form_reports_errors_in_english_on_the_english_site(): void
    {
        $this->from('/en/contact')->post('/en/contact', [])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);
    }

    public function test_contact_honeypot_message_follows_the_page_locale(): void
    {
        $this->from('/ar/contact')->post('/ar/contact', [
            'name' => 'زائر',
            'email' => 'visitor@example.com',
            'subject' => 'سؤال',
            'message' => 'رسالة',
            'website' => 'https://spam.example',
        ])->assertSessionHasErrors(['form' => 'تعذر إرسال النموذج.']);
    }

    public function test_contact_form_marks_required_fields(): void
    {
        $html = $this->get('/ar/contact')
            ->assertOk()
            ->assertSee('الحقول المشار إليها بعلامة * مطلوبة.')
            ->getContent();

        // name, email, subject and message are all required.
        $this->assertSame(4, substr_count((string) $html, '<span class="ms-0.5 text-spu-red" aria-hidden="true">*</span>'));
    }

    public function test_suggestions_form_names_fields_in_arabic(): void
    {
        $this->from('/ar/e-services/suggestions-complaints')->post('/ar/e-services/suggestions-complaints', [])
            ->assertSessionHasErrors([
                'fullName' => 'حقل الاسم الكامل مطلوب.',
                'requestType' => 'حقل نوع الطلب مطلوب.',
                'consent' => 'يجب قبول حقل تقديم الموافقة.',
            ]);
    }

    public function test_suggestions_form_accepts_a_webp_attachment(): void
    {
        Storage::fake('local');

        $this->post('/ar/e-services/suggestions-complaints', [
            'fullName' => 'مقدم الطلب',
            'email' => 'webp@example.com',
            'phone' => '+963110000000',
            'requestType' => 'complaint',
            'subject' => 'لقطة شاشة',
            'message' => 'أرفقت لقطة شاشة من الهاتف.',
            'attachment' => UploadedFile::fake()->image('screenshot.webp', 640, 480),
            'consent' => '1',
        ])->assertRedirect('/ar/e-services/suggestions-complaints')
            ->assertSessionHasNoErrors();

        $submission = DynamicFormSubmission::query()->where('form_id', 'suggestions-complaints')->firstOrFail();
        Storage::disk('local')->assertExists($submission->files_json['attachment']['path']);
    }

    public function test_suggestions_form_still_rejects_other_file_types_in_arabic(): void
    {
        $this->from('/ar/e-services/suggestions-complaints')->post('/ar/e-services/suggestions-complaints', [
            'attachment' => UploadedFile::fake()->create('notes.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHasErrors(['attachment' => 'يجب أن يكون حقل المرفق ملفاً من نوع: pdf, jpg, jpeg, png, webp.']);
    }

    public function test_numbers_typed_with_arabic_or_persian_digits_are_accepted(): void
    {
        $this->postJson('/ar/forms/admissions-application/submissions', [
            'fullName' => 'علي حسن',
            'email' => 'arabic-digits@example.com',
            'phone' => '٠٩٤٤ ١٢٣ ٤٥٦',
            'applicantType' => 'new',
            'targetFaculty' => 'medicine',
            'secondaryCertificate' => 'علمي',
            'certificateCountry' => 'سوريا',
            'agreeToTerms' => 'true',
        ])->assertCreated();

        $this->postJson('/ar/forms/activity-registration/submissions', [
            'fullName' => 'سارا',
            'email' => 'persian-digits@example.com',
            'phone' => '۰۹۴۴۱۲۳۴۵۶',
        ])->assertCreated();

        $phones = DynamicFormSubmission::query()->orderBy('id')->get()->map(fn (DynamicFormSubmission $submission): string => $submission->payload_json['phone'])->all();
        $this->assertSame(['0944 123 456', '0944123456'], $phones);
    }

    public function test_a_phone_with_letters_is_still_refused_with_its_own_message(): void
    {
        $this->postJson('/ar/forms/activity-registration/submissions', [
            'fullName' => 'سارا',
            'email' => 'letters@example.com',
            'phone' => '0944 ext 2',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'صيغة حقل رقم الهاتف غير صالحة.');
    }

    public function test_suggestions_form_shows_the_message_under_each_field(): void
    {
        $payload = app(EServicesPageServiceInterface::class)->getSuggestionsComplaintsEditablePayload();
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        app(CmsWorkflowServiceInterface::class)->saveDraft('e_services.suggestions-complaints', $payload, (int) $author->id);
        app(CmsWorkflowServiceInterface::class)->publish('e_services.suggestions-complaints', (int) $author->id);

        $this->followingRedirects()
            ->from('/ar/e-services/suggestions-complaints')
            ->post('/ar/e-services/suggestions-complaints', ['email' => 'not-an-email'])
            ->assertOk()
            ->assertSee('يجب أن يكون حقل البريد الإلكتروني عنوان بريد إلكتروني صالحاً.')
            ->assertSee('حقل نوع الطلب مطلوب.')
            ->assertSee('حقل الموضوع مطلوب.')
            ->assertSee('يجب قبول حقل تقديم الموافقة.')
            ->assertSee('يرجى تصحيح الحقول المشار إليها.')
            ->assertSee('إذا كنت قد أرفقت ملفاً، يرجى إرفاقه مرة أخرى.');
    }

    public function test_dynamic_form_honeypot_message_follows_the_page_locale(): void
    {
        $this->postJson('/ar/forms/conference-registration/submissions', ['website' => 'https://spam.example'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.form.0', 'تعذر إرسال النموذج.');

        $this->postJson('/en/forms/conference-registration/submissions', ['website' => 'https://spam.example'])
            ->assertJsonPath('errors.form.0', 'The form could not be submitted.');
    }

    public function test_closed_job_message_is_arabic_on_the_arabic_site(): void
    {
        Storage::fake('local');

        $this->postJson('/ar/forms/job-application/submissions', $this->jobApplication() + [
            'job_slug' => 'no-such-job',
            'job_id' => 'job-999',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.job_slug.0', 'الوظيفة المختارة غير متاحة للتقديم.');
    }

    /** @return array<string, mixed> */
    private function jobApplication(): array
    {
        return [
            'firstNameAr' => 'سامر',
            'lastNameAr' => 'خليل',
            'email' => 'applicant@example.com',
            'phone' => '+963110000000',
            'gender' => 'male',
            'profession' => 'مهندس',
            'birthDate' => '1990-01-01',
            'educationLevel' => 'phd',
            'highestUniversity' => 'جامعة دمشق',
            'englishLevel' => 'fluent',
            'targetFaculty' => 'ai',
            'generalSpecialization' => 'هندسة',
            'preciseSpecialization' => 'ذكاء اصطناعي',
            'academicRank' => 'lecturer',
            'contractType' => 'full-time',
            'cvFile' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
            'hasPriorCriminalRecord' => 'no',
            'canProvideReferences' => 'yes',
            'agreeToTerms' => '1',
        ];
    }

    /**
     * @param  array<string, mixed>  $messages
     * @return list<string>
     */
    private function messageKeys(array $messages, string $prefix = ''): array
    {
        $keys = [];

        foreach ($messages as $key => $value) {
            if ($prefix === '' && in_array($key, ['custom', 'attributes'], true)) {
                continue;
            }

            $keys = is_array($value)
                ? [...$keys, ...$this->messageKeys($value, $prefix.$key.'.')]
                : [...$keys, $prefix.$key];
        }

        return $keys;
    }
}
