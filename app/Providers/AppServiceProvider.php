<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Contracts\Tenant\SmsGatewayInterface::class, function ($app) {
            try {
                if (function_exists('tenant') && tenant()) {
                    $provider = \App\Models\Tenant\Setting::get('sms_provider', 'mock');
                    if ($provider === 'twilio') {
                        return new \App\Services\Tenant\Gateways\TwilioSmsGateway();
                    }
                }
            } catch (\Exception $e) {
                // Fallback to mock gateway if database or settings lookup fails
            }
            return new \App\Services\Tenant\Gateways\MockSmsGateway();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
