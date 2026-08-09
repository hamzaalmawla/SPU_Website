<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates or updates the explicitly configured HR admin account.
 */
final class HrUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) env('HR_EMAIL', '')));
        $password = (string) env('HR_PASSWORD', '');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('HR_EMAIL must be configured before running HrUserSeeder.');
        }

        if (! $this->isStrongPassword($password)) {
            throw new \RuntimeException('HR_PASSWORD must be at least 14 characters before running HrUserSeeder.');
        }

        $role = Role::query()->where('slug', 'hr')->firstOrFail();
        $user = User::query()->withTrashed()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => (string) env('HR_NAME', 'SPU Human Resources'),
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'role_id' => (int) $role->getKey(),
            'role_slug' => 'hr',
            'failed_login_attempts' => 0,
            'failed_attempts' => 0,
            'is_locked' => false,
            'locked_at' => null,
            'last_login_at' => null,
            'faculty_scope_slug' => null,
            'deleted_at' => null,
        ])->save();
    }

    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 14;
    }
}
