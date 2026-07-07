<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantIsActive
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
        try {
            $tenant = tenancy()->tenant();

            if (!$tenant || $tenant->status !== 'active') {
                return response()->json(['error' => 'Tenant is not active'], 403);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Tenant validation failed'], 500);
        }

        return $next($request);
    }
}
