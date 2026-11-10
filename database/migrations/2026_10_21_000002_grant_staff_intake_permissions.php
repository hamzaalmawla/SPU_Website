<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Staff intake links and their review queue are new permissions. Editors get
 * all of them; faculty editors get them too, limited as always to their own
 * faculties, since they make the links and review what their staff send.
 */
return new class extends Migration
{
    private const GRANTS = [
        'editor' => ['staff_intake_links.view', 'staff_intake_links.create', 'staff_intake_links.update', 'staff_intake_requests.view', 'staff_intake_requests.update'],
        'faculty_editor' => ['staff_intake_links.view', 'staff_intake_links.create', 'staff_intake_links.update', 'staff_intake_requests.view', 'staff_intake_requests.update'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::GRANTS as $slug => $permissions) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            if ($roleId === null) {
                continue;
            }

            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission' => $permission,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', self::GRANTS['editor'])->delete();
    }
};
