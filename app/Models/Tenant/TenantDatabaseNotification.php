<?php

namespace App\Models\Tenant;

use Illuminate\Notifications\DatabaseNotification;

class TenantDatabaseNotification extends DatabaseNotification
{
    /**
     * Enforce tenant database connection
     */
    protected $connection = 'tenant';
}
