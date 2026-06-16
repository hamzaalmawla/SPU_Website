<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportLegacyAdminsSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'admins';
        $batch = $this->batchName($module);
        $rows = $this->legacyRows('jx_admins');

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_admins', $sourceId, 'users')) {
                continue;
            }

            $email = $this->emailValidator()->normalize($this->cleanedString($row, ['email', 'mail']));

            if ($email === null) {
                $this->reject($module, 'jx_admins', $sourceId, 'invalid_email', 'Legacy admin row has no valid email.', ['email' => $this->rowValue($row, ['email', 'mail'])]);
                $this->logSkip($module, $batch, 'jx_admins', $sourceId, 'users', 'Skipped admin import due to invalid email.');

                continue;
            }

            $existingUser = User::withTrashed()->where('email', $email)->first();

            if ($existingUser instanceof User) {
                $this->reject($module, 'jx_admins', $sourceId, 'duplicate_conflict', 'A user with the same email already exists.', ['email' => $email]);
                $this->logSkip($module, $batch, 'jx_admins', $sourceId, 'users', 'Skipped duplicate admin email.', ['email' => $email]);

                continue;
            }

            $roleSlug = $this->normalizedBoolean($this->rowValue($row, ['is_supervisor', 'supervisor', 'is_admin']))
                ? 'super_admin'
                : 'editor';

            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role instanceof Role) {
                $this->reject($module, 'jx_admins', $sourceId, 'unknown_mapping', 'Could not resolve the target role for the legacy admin.', ['role_slug' => $roleSlug]);
                $this->logSkip($module, $batch, 'jx_admins', $sourceId, 'users', 'Skipped admin import due to missing role mapping.');

                continue;
            }

            $user = new User;
            $createdAt = $this->dateNormalizer()->normalize($this->rowValue($row, ['reg_date', 'created_at', 'date_added']))?->toDateTimeString();
            $name = $this->cleanedString($row, ['full_name', 'user_name', 'name', 'username']) ?? 'Legacy Admin '.($sourceId ?? Str::random(6));

            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
                'role_id' => (int) $role->getKey(),
                'role_slug' => $roleSlug,
                'failed_login_attempts' => 0,
                'failed_attempts' => 0,
                'is_locked' => true,
                'locked_at' => now(),
                'last_login_at' => null,
                'faculty_scope_slug' => $this->cleanedString($row, ['faculty_scope_slug', 'scope_slug']),
                'deleted_at' => null,
                'created_at' => $createdAt ?? $user->created_at ?? now(),
                'updated_at' => now(),
            ]);
            $user->save();

            $this->migrationLogger()->log(
                $module,
                $batch,
                'jx_admins',
                $sourceId,
                'users',
                (int) $user->getKey(),
                'success',
                'Imported legacy admin as locked user pending credential reset.',
                ['email' => $email, 'role_slug' => $roleSlug],
            );
        }
    }
}
