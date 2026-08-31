<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if ($this->app === null) {
            $this->refreshApplication();
        }

        if (! app()->environment('testing')) {
            throw new RuntimeException('Tests may only run with APP_ENV=testing. Clear cached configuration before testing.');
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $isEphemeralSqlite = $connection === 'sqlite' && ($database === ':memory:' || $database === '');

        if (! $isEphemeralSqlite && ! str_contains(strtolower($database), 'test')) {
            throw new RuntimeException("Refusing to run tests against non-testing database [{$database}].");
        }

        parent::setUp();
    }
}
