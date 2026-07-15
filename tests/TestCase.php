<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    protected function migrateFreshUsing()
    {
        return [
            '--path' => [
                database_path('migrations/master'),
                database_path('migrations/0001_01_01_000001_create_cache_table.php'),
                database_path('migrations/0001_01_01_000002_create_jobs_table.php'),
            ],
            '--realpath' => true,
        ];
    }

    protected function connectionsToTransact()
    {
        return ['sqlite', 'tenant'];
    }

    protected function beforeRefreshingDatabase()
    {
        $dbPath = database_path('tenant_testing.sqlite');
        if (!file_exists($dbPath)) {
            touch($dbPath);
        }

        $this->artisan('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);
    }
}
