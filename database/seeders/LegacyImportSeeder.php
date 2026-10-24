<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\LegacyImport\ImportLegacyAdminsSeeder;
use Database\Seeders\LegacyImport\ImportLegacyAlumniSeeder;
use Database\Seeders\LegacyImport\ImportLegacyCareerLinksSeeder;
use Database\Seeders\LegacyImport\ImportLegacyCitiesSeeder;
use Database\Seeders\LegacyImport\ImportLegacyComplaintsSeeder;
use Database\Seeders\LegacyImport\ImportLegacyCouncilsSeeder;
use Database\Seeders\LegacyImport\ImportLegacyCountriesSeeder;
use Database\Seeders\LegacyImport\ImportLegacyFacultiesSeeder;
use Database\Seeders\LegacyImport\ImportLegacyFacultyMembersSeeder;
use Database\Seeders\LegacyImport\ImportLegacyFaqsSeeder;
use Database\Seeders\LegacyImport\ImportLegacyHomepageSeeder;
use Database\Seeders\LegacyImport\ImportLegacyHonorStudentsSeeder;
use Database\Seeders\LegacyImport\ImportLegacyLinksSeeder;
use Database\Seeders\LegacyImport\ImportLegacyNewsSeeder;
use Database\Seeders\LegacyImport\ImportLegacyResearchSeeder;
use Database\Seeders\LegacyImport\ImportLegacySettingsSeeder;
use Database\Seeders\LegacyImport\ImportLegacyStaticPagesSeeder;
use Illuminate\Database\Seeder;

/**
 * Explicit entry point for legacy migration/import support seeders.
 */
class LegacyImportSeeder extends Seeder
{
    public function run(): void
    {
        if (! filter_var(config('old_database.allow_broad_import', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Broad legacy import blocked. Set OLD_DB_ALLOW_BROAD_IMPORT=true and enable each module explicitly to run this seeder.');

            return;
        }

        $this->call([
            ImportLegacyCountriesSeeder::class,
            ImportLegacyCitiesSeeder::class,
            ImportLegacyFacultiesSeeder::class,
            ImportLegacyFacultyMembersSeeder::class,
            ImportLegacyCouncilsSeeder::class,
            ImportLegacyResearchSeeder::class,
            ImportLegacyNewsSeeder::class,
            ImportLegacyFaqsSeeder::class,
            ImportLegacyComplaintsSeeder::class,
            ImportLegacyCareerLinksSeeder::class,
            ImportLegacyHonorStudentsSeeder::class,
            ImportLegacyAlumniSeeder::class,
            ImportLegacyHomepageSeeder::class,
            ImportLegacyStaticPagesSeeder::class,
            ImportLegacySettingsSeeder::class,
            ImportLegacyLinksSeeder::class,
            ImportLegacyAdminsSeeder::class,
        ]);
    }
}
