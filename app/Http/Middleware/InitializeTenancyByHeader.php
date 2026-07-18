<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Master\Tenant;

class InitializeTenancyByHeader
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
        $slug = $request->header('X-Tenant') 
            ?? $request->header('X-Tenant-Slug') 
            ?? $request->header('X-Company-Slug');

        if (!$slug) {
            // Fallback: extrai o slug a partir do subdomínio/host
            $slug = $this->extractSlugFromSubdomain($request);
        }

        if ($slug) {
            try {
                $tenant = Tenant::where('slug', $slug)->active()->first();

                if ($tenant) {
                    tenancy()->initialize($tenant);
                }
            } catch (\Exception $e) {
                // Silenciosamente ignora falhas de inicialização aqui para que outros handlers possam tratar
            }
        }

        return $next($request);
    }

    /**
     * Extrai o slug do tenant a partir do subdomínio.
     * Exemplo: oficina-a.prime-erp.local → oficina-a
     *
     * @param  Request  $request
     * @return string|null
     */
    protected function extractSlugFromSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        foreach ($centralDomains as $domain) {
            if ($host === $domain) {
                continue;
            }

            if (str_ends_with($host, '.' . $domain)) {
                $subdomain = str_replace('.' . $domain, '', $host);

                // Evita retornar subdomínios reservados do painel central
                if ($subdomain && $subdomain !== 'api' && $subdomain !== 'admin') {
                    return $subdomain;
                }
            }
        }

        return null;
    }
}
