<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\Master\User::factory()->superAdmin()->create();
$tenant = \App\Models\Master\Tenant::factory()->create();

echo "User Role: " . $user->role . "\n";
echo "User isSuperAdmin: " . ($user->isSuperAdmin() ? 'yes' : 'no') . "\n";
echo "Tenant Class: " . get_class($tenant) . "\n";
$policy = \Illuminate\Support\Facades\Gate::getPolicyFor($tenant);
echo "Policy for Tenant: " . ($policy ? get_class($policy) : 'none') . "\n";
echo "Can view Tenant: " . ($user->can('view', $tenant) ? 'yes' : 'no') . "\n";
