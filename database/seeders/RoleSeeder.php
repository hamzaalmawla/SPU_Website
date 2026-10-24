<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->roles() as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name']]
            );
        }
    }

    /**
     * @return array<int, array{name: string, slug: string}>
     */
    private function roles(): array
    {
        return [
            ['name' => 'Super Admin', 'slug' => 'super_admin'],
            ['name' => 'Editor', 'slug' => 'editor'],
            ['name' => 'Faculty Editor', 'slug' => 'faculty_editor'],
            ['name' => 'Human Resources', 'slug' => 'hr'],
        ];
    }
}
