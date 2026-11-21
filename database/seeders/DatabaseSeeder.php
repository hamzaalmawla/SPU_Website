<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (config('app.env') === 'production') {
            throw new RuntimeException('DatabaseSeeder is disabled in production. Use an approved, non-destructive migration or import workflow.');
        }

        $this->call(ProductionFoundationSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(LocalDevelopmentSeeder::class);
        }
    }
}
