<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        if ($connection !== 'mysql' || $database !== 'prime_forecast_v3_test') {
            throw new \RuntimeException(
                "Unsafe test database [{$connection}:{$database}]. Expected mysql:prime_forecast_v3_test."
            );
        }
    }
}
