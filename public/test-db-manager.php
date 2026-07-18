<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $tenant = \App\Models\Master\Tenant::where('slug', 'empresa-demo')->first();
    if (!$tenant) {
        echo "Tenant not found!\n";
        exit;
    }
    
    echo "Tenant DB Name: " . $tenant->database()->getName() . "\n";
    echo "Tenant DB Host: " . $tenant->db_host . "\n";
    echo "Tenant DB Driver: " . $tenant->db_driver . "\n";
    
    echo "Attempting to create database physically...\n";
    $tenant->database()->manager()->createDatabase($tenant);
    echo "Database created physically!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
