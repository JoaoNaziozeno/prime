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

        // Se for um Super Admin, não inicializa o tenant
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Se for um Cliente (Portal do Cliente), a conexão do tenant já foi inicializada via Header
        if ($user instanceof \App\Models\Tenant\Customer) {
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