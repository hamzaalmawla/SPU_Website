<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
}
