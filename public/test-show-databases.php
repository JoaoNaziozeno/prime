<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$databases = \Illuminate\Support\Facades\DB::select('SHOW DATABASES');
echo json_encode($databases, JSON_PRETTY_PRINT);
