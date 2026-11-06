<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The centers pages (/centers and each center's pages) are a new component.
 * Editors, who edit every other fixed site page, get it; super admins can
 * change that under Administration › Roles.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['centers.view', 'centers.update', 'centers.publish'];

    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', 'editor')->value('id');
        if ($roleId === null) {
            return;
        }

        $now = now();
        foreach (self::PERMISSIONS as $permission) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission' => $permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', self::PERMISSIONS)->delete();
    }
};
