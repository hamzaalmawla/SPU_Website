<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class OldDatabaseConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_connection_uses_dedicated_alias_when_configured_name_is_default_connection(): void
    {
        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config()->set('old_database.connection_name', (string) config('database.default'));
        config()->set('old_database.connection', $connection);

        $oldDatabase = app(OldDatabaseConnection::class);

        $this->assertSame('legacy_mysql', $oldDatabase->connectionName());

        $oldDatabase->connection()->getSchemaBuilder()->create('legacy_only', function ($schema): void {
            $schema->increments('id');
        });

        $this->assertTrue(Schema::connection('legacy_mysql')->hasTable('legacy_only'));
    }
}
