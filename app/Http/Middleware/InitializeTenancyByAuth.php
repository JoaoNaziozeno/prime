<?php 

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Verifica se o usuário está logado
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Você precisa estar logado para acessar este recurso.', ], 401);
        }

        // Se for um Super Admin
        if ($user->role === 'super_admin') {
            if (!function_exists('tenant') || !tenant()) {
                $slug = $request->header('X-Tenant') ?? $request->header('X-Tenant-Slug');
                if ($slug) {
                    $tenant = \App\Models\Master\Tenant::where('slug', $slug)->active()->first();
                    if ($tenant) {
                        tenancy()->initialize($tenant);
                    }
                } else {
                    $company = $user->companies()->first();
                    if ($company && ($tenant = $company->activeTenant()->first())) {
                        tenancy()->initialize($tenant);
                    }
                }
            }
            return $next($request);
        }

        // Se for um Cliente (Portal do Cliente), a conexão do tenant já foi inicializada via Header
        if ($user instanceof \App\Models\Tenant\Customer) {
            return $next($request);
        }

        // Se a tenancy já foi inicializada (ex: via InitializeTenancyByHeader)
        if (function_exists('tenant') && tenant()) {
            return $next($request);
        }

        // Buscar a empresa (Company) associada a este usuário no banco central
        $company = $user->companies()->first();
        if (!$company) {
            return response()->json(['error' => 'Usuário não associado a nenhuma empresa.'], 403);
        }
        // Buscar o Tenant ativo desta empresa
        $tenant = $company->activeTenant()->first();
        if (!$tenant) {
            return response()->json(['error' => 'Empresa ativa não possui banco de dados configurado.'], 403);
        }
        // Inicializar o banco de dados dinamicamente para esta requisição!
        tenancy()->initialize($tenant);
        return $next($request);
    }
}