<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Staff\StaffIntakeSubmissionDTO;
use App\Services\Staff\StaffIntakeFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

final class StaffIntakeSubmitRequest extends FormRequest
{
    private const EASTERN_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** Arabic keyboards type ٠١٢…, which the phone and year rules would refuse. */
    protected function prepareForValidation(): void
    {
        $normalized = [];
        if (is_string($this->input('phone'))) {
            $normalized['phone'] = trim(strtr($this->input('phone'), self::EASTERN_DIGITS));
        }

        $educations = $this->input('educations');
        if (is_array($educations)) {
            foreach ($educations as $index => $row) {
                foreach (['year_start', 'year_end'] as $key) {
                    if (is_array($row) && is_string($row[$key] ?? null)) {
                        $educations[$index][$key] = trim(strtr($row[$key], self::EASTERN_DIGITS));
                    }
                }
            }
            $normalized['educations'] = array_values(array_filter($educations, 'is_array'));
        }

        $this->merge($normalized);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $year = (int) date('Y') + 10;
        $rules = [
            'ref' => ['required', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:255'],
            'confirm_not_duplicate' => ['nullable', 'boolean'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'office_location' => ['nullable', 'string', 'max:255'],
            'educations' => ['nullable', 'array', 'max:20'],
            'educations.*.id' => ['nullable', 'integer'],
            'educations.*.year_start' => ['nullable', 'integer', 'between:1950,'.$year],
            'educations.*.year_end' => ['nullable', 'integer', 'between:1950,'.$year],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'cv' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:5120'],
        ];

        foreach (['ar', 'en'] as $locale) {
            $rules['title_'.$locale] = ['nullable', 'string', 'max:255'];
            $rules['position_'.$locale] = ['nullable', 'string', 'max:255'];
            $rules['bio_'.$locale] = ['nullable', 'string', 'max:5000'];
            $rules['specializations_'.$locale] = ['nullable', 'string', 'max:1000'];
            $rules['educations.*.degree_'.$locale] = ['nullable', 'string', 'max:255'];
            $rules['educations.*.institution_'.$locale] = ['nullable', 'string', 'max:255'];
            $rules['educations.*.field_'.$locale] = ['nullable', 'string', 'max:255'];
        }

        foreach (StaffIntakeFields::LINKS as $link) {
            $rules[$link] = ['nullable', 'url:http,https', 'max:255'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $locale = $this->locale();
        $attributes = [];
        foreach ([...StaffIntakeFields::keys(), 'photo', 'cv'] as $key) {
            $attributes[$key] = __('staff_intake.fields.'.$key, [], $locale);
        }
        foreach (StaffIntakeFields::EDUCATION as $key) {
            $attributes['educations.*.'.$key] = __('staff_intake.education.'.$key, [], $locale);
        }

        return $attributes;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (is_string($this->input('website')) && trim($this->input('website')) !== '') {
                $validator->errors()->add('form', __('staff_intake.public.errors.not_sent', [], $this->locale()));
            }
        });
    }

    public function toDto(string $token, string $locale): StaffIntakeSubmissionDTO
    {
        $validated = $this->validated();
        $photo = $this->file('photo');
        $cv = $this->file('cv');

        return new StaffIntakeSubmissionDTO(
            token: $token,
            ref: (string) $validated['ref'],
            locale: $locale,
            fields: array_intersect_key($validated, array_flip(StaffIntakeFields::keys())),
            educations: array_values(is_array($validated['educations'] ?? null) ? $validated['educations'] : []),
            photo: $photo instanceof UploadedFile ? $photo : null,
            cv: $cv instanceof UploadedFile ? $cv : null,
            confirmedNotDuplicate: $this->boolean('confirm_not_duplicate'),
            ipAddress: $this->ip(),
            userAgent: $this->userAgent(),
        );
    }

    private function locale(): string
    {
        return $this->route('locale') === 'en' ? 'en' : 'ar';
    }
}
