<?php

declare(strict_types=1);

namespace App\Services\Form;

use App\Contracts\Form\DynamicFormSubmissionServiceInterface;
use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Contracts\Form\FormSubmissionNotificationServiceInterface;
use App\Contracts\News\NewsArticleEventServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\CampusLife\CampusLifeJobDTO;
use App\DTOs\Form\DynamicFormSubmissionDataDTO;
use App\DTOs\News\NewsArticleEventDTO;
use App\DTOs\News\NewsEventDTO;
use App\DTOs\Research\ResearchConferenceRegistrationDTO;
use App\Enums\FormSubmissionInbox;
use App\Models\Form\DynamicFormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DynamicFormSubmissionService implements DynamicFormSubmissionServiceInterface
{
    public function __construct(
        private readonly NewsServiceInterface $newsService,
        private readonly CampusLifePageServiceInterface $campusLifePageService,
        private readonly ResearchPageServiceInterface $researchPageService,
        private readonly FormSubmissionNotificationServiceInterface $notificationService,
        private readonly FormDefinitionServiceInterface $formDefinitions,
        private readonly NewsArticleEventServiceInterface $articleEvents,
    ) {}

    /** @return array<int, string> */
    public function allowedFormIds(): array
    {
        return $this->formDefinitions->enabledKeys();
    }

    /** @return array<int, array<string, mixed>> */
    public function formSchema(string $formId): array
    {
        return $this->formDefinitions->isEnabled($formId)
            ? $this->formDefinitions->find($formId)?->fields ?? []
            : [];
    }

    /** @return array<string, array<int, string>> */
    public function validationRules(string $formId): array
    {
        $rules = [];

        foreach ($this->formSchema($formId) as $field) {
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
                $fieldRules[] = 'mimes:'.(str_replace('.', '', (string) ($field['accept'] ?? '')) ?: 'pdf');
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

        $inbox = $this->formDefinitions->inboxFor($data->formId);

        if ($inbox === FormSubmissionInbox::JOBS) {
            $payload['_context'] = $this->validatedJobContext($data);
        }

        if ($inbox === FormSubmissionInbox::ADMISSIONS) {
            $payload['_context'] = ['source' => 'admissions'];
        }

        if ($inbox === FormSubmissionInbox::SUGGESTIONS) {
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
            NewsArticleEventServiceInterface::REGISTRATION_SOURCE => $data->eventId !== null && ctype_digit($data->eventId)
                ? $this->articleEvents->findPublicEvent($data->eventId, $data->locale)
                : null,
            default => null,
        };

        if ($event instanceof NewsEventDTO) {
            if (! $event->isRegisterable || $event->formId !== $data->formId) {
                throw ValidationException::withMessages(['event_id' => [__('forms.errors.event_not_open', [], $data->locale)]]);
            }
        } elseif ($event instanceof NewsArticleEventDTO) {
            // "full" is reported as full; any other closed state as not open.
            if ($event->registrationState === 'full') {
                throw ValidationException::withMessages(['event_id' => [__('forms.errors.event_full', [], $data->locale)]]);
            }
            if ($event->registrationState !== 'open' || $event->formId !== $data->formId) {
                throw ValidationException::withMessages(['event_id' => [__('forms.errors.event_not_open', [], $data->locale)]]);
            }
        } elseif ($event instanceof ResearchConferenceRegistrationDTO) {
            if ($event->formId !== $data->formId) {
                throw ValidationException::withMessages(['event_id' => [__('forms.errors.event_not_open', [], $data->locale)]]);
            }
        } else {
            throw ValidationException::withMessages(['event_id' => [__('forms.errors.event_not_open', [], $data->locale)]]);
        }

        // Registrations are matched by the event they name, whichever form an
        // editor attached to it.
        $email = is_string($data->payload['email'] ?? null) ? mb_strtolower(trim($data->payload['email'])) : '';
        $registrations = DynamicFormSubmission::query()
            ->whereRaw('LOWER(applicant_email) = ?', [$email])
            ->where('payload_json', 'like', '%"event_id":'.json_encode($event->id).'%')
            ->get();

        if ($registrations->contains(fn (DynamicFormSubmission $submission): bool => ($submission->payload_json['_context']['source'] ?? null) === $data->eventSource
            && ($submission->payload_json['_context']['event_id'] ?? null) === $event->id)) {
            throw ValidationException::withMessages(['email' => [__('forms.errors.already_registered', [], $data->locale)]]);
        }

        if ($event instanceof NewsEventDTO) {
            $submittedCount = DynamicFormSubmission::query()
                ->where('payload_json', 'like', '%"event_id":'.json_encode($event->id).'%')
                ->get()
                ->filter(fn (DynamicFormSubmission $submission): bool => ($submission->payload_json['_context']['source'] ?? null) === 'news-events'
                    && ($submission->payload_json['_context']['event_id'] ?? null) === $event->id)
                ->count();

            if ($event->capacity !== null && $event->registered + $submittedCount >= $event->capacity) {
                throw ValidationException::withMessages(['event_id' => [__('forms.errors.event_full', [], $data->locale)]]);
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
            throw ValidationException::withMessages(['job_slug' => [__('forms.errors.job_not_open', [], $data->locale)]]);
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
}
