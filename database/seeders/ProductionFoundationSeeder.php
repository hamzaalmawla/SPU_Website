<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds only structural data that is safe across all environments.
 */
class ProductionFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            HomepageSectionSeeder::class,
            AboutSectionSeeder::class,
            FacultyModuleSeeder::class,
            NewsCategorySeeder::class,
        ]);
    }
}
