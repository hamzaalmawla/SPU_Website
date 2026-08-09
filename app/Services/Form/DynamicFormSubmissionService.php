<?php

declare(strict_types=1);

namespace App\Services\Form;

use App\Contracts\Form\DynamicFormSubmissionServiceInterface;
use App\Contracts\Form\FormSubmissionNotificationServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\CampusLife\CampusLifeJobDTO;
use App\DTOs\Form\DynamicFormSubmissionDataDTO;
use App\DTOs\News\NewsEventDTO;
use App\DTOs\Research\ResearchConferenceRegistrationDTO;
use App\Models\Form\DynamicFormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DynamicFormSubmissionService implements DynamicFormSubmissionServiceInterface
{
    /** @var array<int, string> */
    private const EVENT_REGISTRATION_FORMS = ['conference-registration', 'symposium-registration', 'activity-registration'];

    public function __construct(
        private readonly NewsServiceInterface $newsService,
        private readonly CampusLifePageServiceInterface $campusLifePageService,
        private readonly ResearchPageServiceInterface $researchPageService,
        private readonly FormSubmissionNotificationServiceInterface $notificationService,
    ) {}

    /** @return array<int, string> */
    public function allowedFormIds(): array
    {
        return array_keys($this->schemas());
    }

    /** @return array<int, array<string, mixed>> */
    public function formSchema(string $formId): array
    {
        return $this->schemas()[$formId] ?? [];
    }

    /** @return array<string, array<int, string>> */
    public function validationRules(string $formId): array
    {
        $schema = $this->schemas()[$formId] ?? null;

        if (! is_array($schema)) {
            return [];
        }

        $rules = [];

        foreach ($schema as $field) {
            $name = (string) ($field['name'] ?? '');
            $type = (string) ($field['type'] ?? 'text');

            if ($name === '') {
                continue;
            }

            $fieldRules = [(bool) ($field['required'] ?? false) ? 'required' : 'nullable'];

            if ($type === 'email') {
                $fieldRules[] = 'email:rfc';
                $fieldRules[] = 'max:255';
            } elseif ($type === 'select') {
                $options = array_values(array_filter(
                    array_map(static fn (mixed $option): string => is_array($option) ? (string) ($option['value'] ?? '') : '', $field['options'] ?? []),
                    static fn (string $value): bool => $value !== ''
                ));
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:255';

                if ($options !== []) {
                    $fieldRules[] = 'in:'.implode(',', $options);
                }
            } elseif ($type === 'number') {
                $fieldRules[] = 'numeric';
            } elseif ($type === 'tel') {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:30';
                $fieldRules[] = 'regex:/^[0-9+() .-]{7,30}$/';
            } elseif ($type === 'date') {
                $fieldRules[] = 'date';
            } elseif ($type === 'checkbox') {
                $fieldRules = [(bool) ($field['required'] ?? false) ? 'accepted' : 'boolean'];
            } elseif ($type === 'file') {
                $fieldRules[] = 'file';
                $fieldRules[] = 'max:'.(int) ($field['maxKilobytes'] ?? 5120);
                $fieldRules[] = 'mimes:'.(string) ($field['mimes'] ?? 'pdf');
            } elseif ($type === 'textarea') {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:'.(int) ($field['maxLength'] ?? 5000);
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:'.(int) ($field['maxLength'] ?? 1000);
            }

            $rules[$name] = $fieldRules;
        }

        return $rules;
    }

    public function submit(DynamicFormSubmissionDataDTO $data): bool
    {
        if (! in_array($data->formId, $this->allowedFormIds(), true)) {
            throw new \InvalidArgumentException('Unsupported dynamic form.');
        }

        $payload = $data->payload;

        if ($data->eventSource !== null) {
            $payload['_context'] = $this->validatedEventContext($data);
        }

        if ($data->formId === 'job-application') {
            $payload['_context'] = $this->validatedJobContext($data);
        }

        if ($data->formId === 'admissions-application') {
            $payload['_context'] = ['source' => 'admissions'];
        }

        if ($data->formId === 'suggestions-complaints') {
            $payload['_context'] = ['source' => 'e-services-suggestions-complaints'];
        }

        if (is_string($payload['email'] ?? null)) {
            $payload['email'] = mb_strtolower(trim($payload['email']));
        }

        $files = [];

        try {
            foreach ($data->files as $field => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $files[$field] = $this->storeFile($data->formId, $file);
            }

            $submission = DynamicFormSubmission::query()->create([
                'reference_number' => 'SPU-FORM-'.strtoupper((string) Str::ulid()),
                'form_id' => $data->formId,
                'locale' => $data->locale,
                'applicant_name' => $this->applicantName($payload),
                'applicant_email' => is_string($payload['email'] ?? null) ? $payload['email'] : null,
                'status' => 'new',
                'status_changed_at' => now(),
                'email_delivery_status' => is_string($payload['email'] ?? null) ? 'pending' : 'not_applicable',
                'payload_json' => $payload,
                'files_json' => $files,
                'ip_address' => $data->ipAddress,
                'user_agent' => $data->userAgent,
            ]);
        } catch (Throwable $exception) {
            foreach ($files as $metadata) {
                if (($metadata['disk'] ?? null) === 'local' && is_string($metadata['path'] ?? null)) {
                    Storage::disk('local')->delete($metadata['path']);
                }
            }

            throw $exception;
        }

        $this->notificationService->queueDynamicReceived((int) $submission->getKey());

        return true;
    }

    /** @return array{source: string, event_id: string, event_title: string} */
    private function validatedEventContext(DynamicFormSubmissionDataDTO $data): array
    {
        $event = match ($data->eventSource) {
            'news-events' => $data->eventId !== null
                ? $this->newsService->findNewsEvent($data->eventId, $data->locale, false)
                : null,
            'research-conferences' => $data->eventId !== null
                ? $this->researchPageService->findRegisterableConference($data->eventId, $data->locale)
                : null,
            default => null,
        };

        if ($event instanceof NewsEventDTO) {
            if (! $event->isRegisterable || $event->formId !== $data->formId) {
                throw ValidationException::withMessages(['event_id' => ['The selected event is not open for this registration form.']]);
            }
        } elseif ($event instanceof ResearchConferenceRegistrationDTO) {
            if ($event->formId !== $data->formId) {
                throw ValidationException::withMessages(['event_id' => ['The selected event is not open for this registration form.']]);
            }
        } else {
            throw ValidationException::withMessages(['event_id' => ['The selected event is not open for this registration form.']]);
        }

        $email = is_string($data->payload['email'] ?? null) ? mb_strtolower(trim($data->payload['email'])) : '';
        $registrations = DynamicFormSubmission::query()
            ->whereIn('form_id', self::EVENT_REGISTRATION_FORMS)
            ->whereRaw('LOWER(applicant_email) = ?', [$email])
            ->get();

        if ($registrations->contains(fn (DynamicFormSubmission $submission): bool => ($submission->payload_json['_context']['source'] ?? null) === $data->eventSource
            && ($submission->payload_json['_context']['event_id'] ?? null) === $event->id)) {
            throw ValidationException::withMessages(['email' => ['This email is already registered for the selected event.']]);
        }

        if ($event instanceof NewsEventDTO) {
            $submittedCount = DynamicFormSubmission::query()
                ->whereIn('form_id', ['conference-registration', 'activity-registration'])
                ->get()
                ->filter(fn (DynamicFormSubmission $submission): bool => ($submission->payload_json['_context']['source'] ?? null) === 'news-events'
                    && ($submission->payload_json['_context']['event_id'] ?? null) === $event->id)
                ->count();

            if ($event->capacity !== null && $event->registered + $submittedCount >= $event->capacity) {
                throw ValidationException::withMessages(['event_id' => ['The selected event has reached capacity.']]);
            }
        }

        return [
            'source' => (string) $data->eventSource,
            'event_id' => $event->id,
            'event_title' => $event->title,
        ];
    }

    /** @return array{source: string, job_id: string, job_slug: string, job_title: string} */
    private function validatedJobContext(DynamicFormSubmissionDataDTO $data): array
    {
        $job = $data->jobSlug !== null
            ? $this->campusLifePageService->findOpenCareerJob($data->jobSlug, $data->locale)
            : null;

        if (! $job instanceof CampusLifeJobDTO || ! $job->applicationEligible || $data->jobId === null || $job->id !== $data->jobId) {
            throw ValidationException::withMessages(['job_slug' => ['The selected job is not open for applications.']]);
        }

        return [
            'source' => 'campus-life-jobs',
            'job_id' => $job->id,
            'job_slug' => $job->slug,
            'job_title' => $job->title,
        ];
    }

    /** @return array<string, string|int|null> */
    private function storeFile(string $formId, UploadedFile $file): array
    {
        $extension = strtolower((string) $file->extension());

        if ($extension === '' || ! preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
            throw new \RuntimeException('The submission attachment has an unsafe file type.');
        }

        $path = Storage::disk('local')->putFileAs(
            'dynamic-form-submissions/'.$formId.'/'.now()->format('Y/m'),
            $file,
            (string) Str::uuid().'.'.$extension
        );

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('The submission attachment could not be stored.');
        }

        return [
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function applicantName(array $payload): ?string
    {
        if (is_string($payload['fullName'] ?? null) && trim($payload['fullName']) !== '') {
            return trim($payload['fullName']);
        }

        $name = trim(((string) ($payload['firstNameAr'] ?? '')).' '.((string) ($payload['lastNameAr'] ?? '')));

        return $name !== '' ? $name : null;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function schemas(): array
    {
        return [
            'conference-registration' => [
                ['name' => 'fullName', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'required' => false],
                ['name' => 'affiliation', 'type' => 'text', 'required' => true],
                ['name' => 'role', 'type' => 'select', 'required' => true, 'options' => [['value' => 'attendee'], ['value' => 'presenter'], ['value' => 'poster'], ['value' => 'moderator']]],
                ['name' => 'dietary', 'type' => 'select', 'required' => false, 'options' => [['value' => 'vegetarian'], ['value' => 'vegan'], ['value' => 'halal'], ['value' => 'other']]],
                ['name' => 'specialNeeds', 'type' => 'textarea', 'required' => false],
            ],
            'symposium-registration' => [
                ['name' => 'fullName', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'required' => false],
                ['name' => 'department', 'type' => 'text', 'required' => true],
                ['name' => 'year', 'type' => 'select', 'required' => true, 'options' => [['value' => '1'], ['value' => '2'], ['value' => '3'], ['value' => '4'], ['value' => '5'], ['value' => 'master'], ['value' => 'phd'], ['value' => 'faculty'], ['value' => 'external']]],
                ['name' => 'specialNeeds', 'type' => 'textarea', 'required' => false],
            ],
            'activity-registration' => [
                ['name' => 'fullName', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'required' => false],
                ['name' => 'studentId', 'type' => 'text', 'required' => false],
                ['name' => 'notes', 'type' => 'textarea', 'required' => false],
            ],
            'admissions-application' => [
                ['name' => 'fullName', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'required' => true],
                ['name' => 'applicantType', 'type' => 'select', 'required' => true, 'options' => [['value' => 'new'], ['value' => 'transfer'], ['value' => 'equivalency'], ['value' => 'international']]],
                ['name' => 'targetFaculty', 'type' => 'select', 'required' => true, 'options' => [['value' => 'medicine'], ['value' => 'dentistry'], ['value' => 'pharmacy'], ['value' => 'artificial-intelligence'], ['value' => 'building-construction-engineering'], ['value' => 'petroleum'], ['value' => 'business-administration']]],
                ['name' => 'secondaryCertificate', 'type' => 'text', 'required' => true],
                ['name' => 'certificateCountry', 'type' => 'text', 'required' => true],
                ['name' => 'notes', 'type' => 'textarea', 'required' => false],
                ['name' => 'agreeToTerms', 'type' => 'checkbox', 'required' => true],
            ],
            'job-application' => [
                ['name' => 'firstNameAr', 'type' => 'text', 'required' => true],
                ['name' => 'lastNameAr', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'required' => true],
                ['name' => 'gender', 'type' => 'select', 'required' => true, 'options' => [['value' => 'male'], ['value' => 'female']]],
                ['name' => 'profession', 'type' => 'text', 'required' => true],
                ['name' => 'birthDate', 'type' => 'date', 'required' => true],
                ['name' => 'address', 'type' => 'text', 'required' => false],
                ['name' => 'educationLevel', 'type' => 'select', 'required' => true, 'options' => [['value' => 'phd'], ['value' => 'master'], ['value' => 'bachelor'], ['value' => 'institute'], ['value' => 'none']]],
                ['name' => 'highestUniversity', 'type' => 'text', 'required' => true],
                ['name' => 'academicExperience', 'type' => 'number', 'required' => false],
                ['name' => 'englishLevel', 'type' => 'select', 'required' => true, 'options' => [['value' => 'native'], ['value' => 'fluent'], ['value' => 'advanced'], ['value' => 'intermediate'], ['value' => 'basic']]],
                ['name' => 'personalSkills', 'type' => 'select', 'required' => false, 'options' => [['value' => 'excellent'], ['value' => 'very-good'], ['value' => 'good'], ['value' => 'acceptable']]],
                ['name' => 'institutionName', 'type' => 'text', 'required' => false],
                ['name' => 'targetFaculty', 'type' => 'select', 'required' => true, 'options' => [['value' => 'ai'], ['value' => 'business'], ['value' => 'construction'], ['value' => 'dentistry'], ['value' => 'medicine'], ['value' => 'petroleum'], ['value' => 'pharmacy']]],
                ['name' => 'generalSpecialization', 'type' => 'text', 'required' => true],
                ['name' => 'preciseSpecialization', 'type' => 'text', 'required' => true],
                ['name' => 'academicRank', 'type' => 'select', 'required' => true, 'options' => [['value' => 'professor'], ['value' => 'associate-professor'], ['value' => 'assistant-professor'], ['value' => 'lecturer'], ['value' => 'teaching-assistant']]],
                ['name' => 'contractType', 'type' => 'select', 'required' => true, 'options' => [['value' => 'full-time'], ['value' => 'part-time'], ['value' => 'visiting'], ['value' => 'contract']]],
                ['name' => 'cvFile', 'type' => 'file', 'required' => true],
                ['name' => 'coverLetter', 'type' => 'textarea', 'required' => false],
                ['name' => 'hasPriorCriminalRecord', 'type' => 'select', 'required' => true, 'options' => [['value' => 'no'], ['value' => 'yes']]],
                ['name' => 'canProvideReferences', 'type' => 'select', 'required' => true, 'options' => [['value' => 'yes'], ['value' => 'no']]],
                ['name' => 'agreeToTerms', 'type' => 'checkbox', 'required' => true],
            ],
            'suggestions-complaints' => [
                ['name' => 'fullName', 'type' => 'text', 'required' => true, 'maxLength' => 120],
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'required' => true],
                ['name' => 'requestType', 'type' => 'select', 'required' => true, 'options' => [['value' => 'suggestion'], ['value' => 'complaint'], ['value' => 'inquiry']]],
                ['name' => 'subject', 'type' => 'text', 'required' => true, 'maxLength' => 180],
                ['name' => 'message', 'type' => 'textarea', 'required' => true, 'maxLength' => 5000],
                ['name' => 'attachment', 'type' => 'file', 'required' => false, 'mimes' => 'pdf,jpg,jpeg,png', 'maxKilobytes' => 5120],
                ['name' => 'consent', 'type' => 'checkbox', 'required' => true],
            ],
        ];
    }
}
