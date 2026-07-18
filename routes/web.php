<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Tenant\CustomerAuthController;
use App\Http\Controllers\API\Master\AuthController;

Route::get('/', function () {
    return view('welcome');
});

// Rota de Login único (Central)
Route::post('/api/auth/login', [AuthController::class, 'login']);

// Customer Portal Public Routes
Route::post('/api/customer/login', [CustomerAuthController::class, 'login'])->middleware('throttle:5,1,login');
Route::post('/api/customer/authenticate', [CustomerAuthController::class, 'authenticate'])->middleware('throttle:5,1,login');
