<?php

declare(strict_types=1);

namespace App\Support\LegacyImport;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class OldDatabaseConnection
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    public function connectionName(): string
    {
        $connectionName = (string) config('old_database.connection_name', 'legacy_mysql');

        if ($connectionName === '') {
            return 'legacy_mysql';
        }

        if ($connectionName === (string) config('database.default') && $this->hasDedicatedLegacyConfig()) {
            return 'legacy_mysql';
        }

        return $connectionName;
    }

    public function connection(): ConnectionInterface
    {
        $connectionName = $this->connectionName();
        $config = config('old_database.connection', []);

        if (is_array($config) && $config !== []) {
            config(['database.connections.'.$connectionName => $config]);
        }

        return $this->database->connection($connectionName);
    }

    public function table(string $table): Builder
    {
        return $this->connection()->table($table);
    }

    public function schema(): SchemaBuilder
    {
        return $this->connection()->getSchemaBuilder();
    }

    private function hasDedicatedLegacyConfig(): bool
    {
        $config = config('old_database.connection', []);

        return is_array($config) && $config !== [];
    }
}
