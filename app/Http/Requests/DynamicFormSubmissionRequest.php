<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\Form\DynamicFormSubmissionServiceInterface;
use App\DTOs\Form\DynamicFormSubmissionDataDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

final class DynamicFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return array_merge(app(DynamicFormSubmissionServiceInterface::class)->validationRules($this->formId()), [
            'event_source' => ['nullable', 'string', 'in:news-events'],
            'event_id' => ['nullable', 'required_with:event_source', 'string', 'max:80'],
            'job_id' => ['nullable', 'string', 'max:80'],
            'job_slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $service = app(DynamicFormSubmissionServiceInterface::class);

            if (! in_array($this->formId(), $service->allowedFormIds(), true)) {
                $validator->errors()->add('form', 'Unsupported dynamic form.');
            }
        });
    }

    public function toDto(string $formId, string $locale): DynamicFormSubmissionDataDTO
    {
        $payload = $this->validated();
        $files = [];
        $eventSource = is_string($payload['event_source'] ?? null) ? $payload['event_source'] : null;
        $eventId = is_string($payload['event_id'] ?? null) ? $payload['event_id'] : null;
        $jobId = is_string($payload['job_id'] ?? null) ? $payload['job_id'] : null;
        $jobSlug = is_string($payload['job_slug'] ?? null) ? $payload['job_slug'] : null;
        unset($payload['event_source'], $payload['event_id'], $payload['job_id'], $payload['job_slug']);

        foreach ($payload as $field => $value) {
            if ($value instanceof UploadedFile) {
                $files[(string) $field] = $value;
                unset($payload[$field]);
            }
        }

        return new DynamicFormSubmissionDataDTO(
            formId: $formId,
            locale: $locale,
            payload: $payload,
            files: $files,
            ipAddress: $this->ip(),
            userAgent: $this->userAgent(),
            eventSource: $eventSource,
            eventId: $eventId,
            jobId: $jobId,
            jobSlug: $jobSlug,
        );
    }

    private function formId(): string
    {
        return (string) $this->route('form');
    }
}
