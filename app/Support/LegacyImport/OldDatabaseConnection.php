<?php

declare(strict_types=1);

namespace App\Support\LegacyImport;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;

class OldDatabaseConnection
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    public function connection(): ConnectionInterface
    {
        $connectionName = (string) config('old_database.connection_name', 'legacy_mysql');
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
}
