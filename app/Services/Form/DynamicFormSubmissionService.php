<?php

declare(strict_types=1);

namespace App\Services\Form;

use App\Contracts\Form\DynamicFormSubmissionServiceInterface;
use App\DTOs\Form\DynamicFormSubmissionDataDTO;
use App\Models\Form\DynamicFormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class DynamicFormSubmissionService implements DynamicFormSubmissionServiceInterface
{
    /** @return array<int, string> */
    public function allowedFormIds(): array
    {
        return array_keys($this->schemas());
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
            } elseif ($type === 'date') {
                $fieldRules[] = 'date';
            } elseif ($type === 'checkbox') {
                $fieldRules = [(bool) ($field['required'] ?? false) ? 'accepted' : 'boolean'];
            } elseif ($type === 'file') {
                $fieldRules[] = 'file';
                $fieldRules[] = 'max:5120';
                $fieldRules[] = 'mimes:pdf';
            } elseif ($type === 'textarea') {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:5000';
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:1000';
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

        $files = [];

        foreach ($data->files as $field => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $files[$field] = $this->storeFile($data->formId, $file);
        }

        DynamicFormSubmission::query()->create([
            'form_id' => $data->formId,
            'locale' => $data->locale,
            'applicant_name' => $this->applicantName($data->payload),
            'applicant_email' => is_string($data->payload['email'] ?? null) ? $data->payload['email'] : null,
            'status' => 'new',
            'payload_json' => $data->payload,
            'files_json' => $files,
            'ip_address' => $data->ipAddress,
            'user_agent' => $data->userAgent,
        ]);

        return true;
    }

    /** @return array<string, string|int|null> */
    private function storeFile(string $formId, UploadedFile $file): array
    {
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $path = Storage::disk('local')->putFileAs(
            'dynamic-form-submissions/'.$formId.'/'.now()->format('Y/m'),
            $file,
            (string) Str::uuid().'.'.$extension
        );

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
        ];
    }
}
