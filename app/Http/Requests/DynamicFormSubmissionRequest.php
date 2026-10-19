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

    /**
     * Arabic keyboards type ٠١٢… (and Persian ones ۰۱۲…), which the phone,
     * number and date rules refuse: a fully filled form was turned away.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (app(DynamicFormSubmissionServiceInterface::class)->formSchema($this->formId()) as $field) {
            $name = (string) ($field['name'] ?? '');
            $value = $this->input($name);

            if (in_array($field['type'] ?? null, ['tel', 'number', 'date'], true) && is_string($value)) {
                $normalized[$name] = trim(strtr($value, self::EASTERN_DIGITS));
            }
        }

        $this->merge($normalized);
    }

    private const EASTERN_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return array_merge(app(DynamicFormSubmissionServiceInterface::class)->validationRules($this->formId()), [
            'event_source' => ['nullable', 'required_with:event_id', 'string', 'in:news-events,research-conferences'],
            'event_id' => ['nullable', 'required_with:event_source', 'string', 'max:80'],
            'job_id' => ['nullable', 'string', 'max:80'],
            'job_slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * Human names for the schema's fields, so a message reads "حقل الاسم الكامل
     * مطلوب" rather than quoting the input name. The labels are the ones the
     * submissions review screen already shows for the same fields.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $locale = $this->locale();
        $attributes = [];

        foreach (app(DynamicFormSubmissionServiceInterface::class)->formSchema($this->formId()) as $field) {
            $name = (string) ($field['name'] ?? '');
            $key = 'form_submissions.fields.'.$name;
            $label = (string) ($locale === 'ar' ? ($field['labelAr'] ?? '') : ($field['labelEn'] ?? ''));

            if ($name === '') {
                continue;
            }

            // Fields an editor added in the admin use the label they gave it.
            if (trans()->has($key, $locale)) {
                $attributes[$name] = __($key, [], $locale);
            } elseif ($label !== '') {
                $attributes[$name] = $label;
            }
        }

        return $attributes;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $service = app(DynamicFormSubmissionServiceInterface::class);

            if (! in_array($this->formId(), $service->allowedFormIds(), true)) {
                $validator->errors()->add('form', __('forms.errors.unsupported_form', [], $this->locale()));
            }

            if (is_string($this->input('website')) && trim($this->input('website')) !== '') {
                $validator->errors()->add('form', __('forms.errors.not_submitted', [], $this->locale()));
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
        unset($payload['event_source'], $payload['event_id'], $payload['job_id'], $payload['job_slug'], $payload['website']);

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

    private function locale(): string
    {
        return $this->route('locale') === 'en' ? 'en' : 'ar';
    }
}
