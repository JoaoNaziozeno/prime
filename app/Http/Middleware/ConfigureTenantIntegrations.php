<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant\Setting;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class ConfigureTenantIntegrations
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (function_exists('tenant') && tenant()) {
            // 1. Dynamic SMTP Email Override
            if ($host = Setting::get('mail_host')) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $host,
                    'mail.mailers.smtp.port' => (int) Setting::get('mail_port', 587),
                    'mail.mailers.smtp.username' => Setting::get('mail_username'),
                    'mail.mailers.smtp.password' => Setting::get('mail_password'),
                    'mail.mailers.smtp.encryption' => Setting::get('mail_encryption', 'tls'),
                    'mail.from.address' => Setting::get('mail_from_address', 'no-reply@prime-erp.com'),
                    'mail.from.name' => Setting::get('mail_from_name', 'ERP Prime'),
                ]);

                // Clear resolved instances so the mailer is reconstructed with dynamic settings
                app()->forgetInstance('mail.manager');
                Mail::clearResolvedInstances();
            }

            // 2. Dynamic S3/MinIO Storage Override
            if ($s3Bucket = Setting::get('s3_bucket')) {
                config([
                    'filesystems.default' => 's3',
                    'filesystems.disks.s3.key' => Setting::get('s3_key'),
                    'filesystems.disks.s3.secret' => Setting::get('s3_secret'),
                    'filesystems.disks.s3.region' => Setting::get('s3_region', 'us-east-1'),
                    'filesystems.disks.s3.bucket' => $s3Bucket,
                    'filesystems.disks.s3.endpoint' => Setting::get('s3_endpoint'),
                    'filesystems.disks.s3.url' => Setting::get('s3_url'),
                    'filesystems.disks.s3.use_path_style_endpoint' => (bool) Setting::get('s3_use_path_style_endpoint', false),
                ]);
            }

            // 3. Dynamic Stripe Credentials Override
            if ($stripeSecret = Setting::get('stripe_secret_key')) {
                config([
                    'services.stripe.secret' => $stripeSecret,
                    'services.stripe.webhook_secret' => Setting::get('stripe_webhook_secret'),
                ]);
            }

            // 4. Dynamic Twilio Credentials Override
            if ($twilioSid = Setting::get('twilio_sid')) {
                config([
                    'services.twilio.sid' => $twilioSid,
                    'services.twilio.auth_token' => Setting::get('twilio_auth_token'),
                    'services.twilio.from' => Setting::get('twilio_from'),
                ]);
            }
        }

        return $next($request);
    }
}
