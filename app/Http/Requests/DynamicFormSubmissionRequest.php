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
        return app(DynamicFormSubmissionServiceInterface::class)->validationRules($this->formId());
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
        );
    }

    private function formId(): string
    {
        return (string) $this->route('form');
    }
}
