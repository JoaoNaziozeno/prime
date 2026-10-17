<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Anti-clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Referrer policy — do not leak URL to external domains
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS: force HTTPS for 1 year (enable only when TLS is guaranteed)
        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content-Security-Policy — default-src self; adjust as the frontend evolves
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'"
        );

        // Permissions-Policy — disable unused sensitive browser features
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        // Remove X-XSS-Protection (obsolete and can introduce vulnerabilities in IE)
        $response->headers->remove('X-XSS-Protection');

        return $response;
    }
}
