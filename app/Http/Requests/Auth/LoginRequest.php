<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\LoginCredentialsDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates admin login submissions.
 */
final class LoginRequest extends FormRequest
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
            'email' => ['required', 'email:filter', 'max:255'],
            'password' => ['required', 'string', 'max:128'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function credentials(): LoginCredentialsDTO
    {
        /** @var array{email: string, password: string, remember?: bool} $validated */
        $validated = $this->validated();

        return new LoginCredentialsDTO(
            email: $validated['email'],
            password: $validated['password'],
            remember: (bool) ($validated['remember'] ?? false),
        );
    }
}
