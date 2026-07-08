<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
}
