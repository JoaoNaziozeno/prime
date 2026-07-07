<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantFileNotFound;
use Stancl\Tenancy\Middleware\InitializeTenancy;

class ResolveTenantBySlug
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $slug = $request->route('tenant') ?? $this->extractSlugFromSubdomain($request);

        if (!$slug) {
            return response()->json(['error' => 'Tenant not identified'], 400);
        }

        try {
            // Resolve tenant by slug
            $tenant = \App\Models\Master\Tenant::where('slug', $slug)->active()->firstOrFail();

            // Initialize tenancy
            tenancy()->initialize($tenant);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Tenant not found or inactive'], 404);
        } catch (TenantFileNotFound $e) {
            return response()->json(['error' => 'Tenant database not configured'], 500);
        }

        return $next($request);
    }

    /**
     * Extract tenant slug from subdomain.
     * Example: empresa.prime-erp.local → empresa
     *
     * @param  Request  $request
     * @return string|null
     */
    protected function extractSlugFromSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        foreach ($centralDomains as $domain) {
            if (str_ends_with($host, $domain)) {
                $subdomain = str_replace('.' . $domain, '', $host);

                // Only return if subdomain is not a central domain prefix
                if ($subdomain && $subdomain !== 'api' && $subdomain !== 'admin') {
                    return $subdomain;
                }
            }
        }

        return null;
    }
}
