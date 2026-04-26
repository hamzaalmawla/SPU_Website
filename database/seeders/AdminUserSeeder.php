<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
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

        $user = User::query()->withTrashed()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => (string) env('ADMIN_NAME', 'SPU Super Admin'),
            'password' => Hash::make((string) env('ADMIN_PASSWORD', 'password')),
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
}
