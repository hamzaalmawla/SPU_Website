<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PublicContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = __('forms.contact_attributes', [], $this->locale());

        return is_array($attributes) ? $attributes : [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (is_string($this->input('website')) && trim($this->input('website')) !== '') {
                $validator->errors()->add('form', __('forms.errors.not_submitted', [], $this->locale()));
            }
        });
    }

    private function locale(): string
    {
        return $this->route('locale') === 'en' ? 'en' : 'ar';
    }
}
