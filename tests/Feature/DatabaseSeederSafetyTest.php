<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class DatabaseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_refuses_production_environment(): void
    {
        config()->set('app.env', 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DatabaseSeeder is disabled in production');

        app(DatabaseSeeder::class)->run();
    }
}
