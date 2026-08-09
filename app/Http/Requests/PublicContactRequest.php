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
        $isArabic = $this->route('locale') === 'ar';

        return [
            'name' => $isArabic ? 'الاسم' : 'name',
            'email' => $isArabic ? 'البريد الإلكتروني' : 'email',
            'subject' => $isArabic ? 'الموضوع' : 'subject',
            'message' => $isArabic ? 'الرسالة' : 'message',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (is_string($this->input('website')) && trim($this->input('website')) !== '') {
                $validator->errors()->add('form', $this->route('locale') === 'ar' ? 'تعذر إرسال النموذج.' : 'The form could not be submitted.');
            }
        });
    }
}
