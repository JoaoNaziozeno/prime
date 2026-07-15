<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\ErpServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    ErpServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
];
