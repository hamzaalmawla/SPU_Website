<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds local/testing scaffolding that should not be assumed in production-like environments.
 */
class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            HomepageSectionTranslationSeeder::class,
            SettingsSeeder::class,
            LandingPageSeeder::class,
            NavigationSeeder::class,
        ]);
    }
}
