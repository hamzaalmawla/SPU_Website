<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a convenience super admin for local development only.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $email = (string) env('ADMIN_EMAIL', 'admin@spu.edu.sy');
        $password = $this->resolveBootstrapPassword();

        $user = User::query()->withTrashed()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => (string) env('ADMIN_NAME', 'SPU Super Admin'),
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'role_id' => (int) $role->getKey(),
            'role_slug' => 'super_admin',
            'failed_login_attempts' => 0,
            'failed_attempts' => 0,
            'is_locked' => false,
            'locked_at' => null,
            'last_login_at' => null,
            'faculty_scope_slug' => null,
            'deleted_at' => null,
        ]);
        $user->save();
    }

    private function resolveBootstrapPassword(): string
    {
        $password = (string) env('ADMIN_PASSWORD', '');

        if (app()->environment(['local', 'testing'])) {
            return $password !== '' ? $password : 'local-development-password';
        }

        if (! $this->isStrongBootstrapPassword($password)) {
            throw new \RuntimeException('ADMIN_PASSWORD must be explicitly configured with a strong one-time password outside local/testing environments.');
        }

        return $password;
    }

    private function isStrongBootstrapPassword(string $password): bool
    {
        $weakValues = [
            '',
            'password',
            'admin',
            'changeme',
            'REPLACE_WITH_ONE_TIME_STRONG_BOOTSTRAP_PASSWORD',
        ];

        if (in_array($password, $weakValues, true)) {
            return false;
        }

        return strlen($password) >= 14;
    }
}
