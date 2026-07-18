<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\CustomerPortalService;
use App\Models\Master\Tenant;
use App\Models\Tenant\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    public function __construct(
        protected CustomerPortalService $customerPortalService
    ) {}

    /**
     * Gera o Magic Link varrendo os Tenants até achar o e-mail do cliente
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = $validated['email'];
        $tenants = Tenant::where('status', 'active')->get();
        $foundTenant = null;
        $token = null;

        // Varrer cada banco de dados de inquilino para achar o e-mail
        foreach ($tenants as $tenant) {
            try {
                // Ativa a conexão com a base dessa oficina temporariamente
                tenancy()->initialize($tenant);

                $customer = Customer::where('email', $email)
                    ->where('status', Customer::STATUS_ACTIVE)
                    ->first();

                if ($customer) {
                    $foundTenant = $tenant;
                    // Gera o token de login na base de dados dessa oficina
                    $token = $this->customerPortalService->generateMagicLink($email);
                    break; // Cliente localizado, encerra a busca
                }
            } catch (\Exception $e) {
                // Se der erro em alguma base específica, ignora e segue para a próxima
                continue;
            }
        }

        if (!$foundTenant) {
            return response()->json([
                'error' => 'Nenhum cliente ativo localizado com este e-mail.'
            ], 404);
        }

        return response()->json([
            'message' => 'Magic Link gerado com sucesso.',
            'token' => $token,
            'tenant_slug' => $foundTenant->slug,
            // A URL de autenticação agora aponta para o domínio único contendo o slug do inquilino
            'login_url' => "http://localhost:8000/customer/authenticate?token={$token}&tenant={$foundTenant->slug}",
        ]);
    }

    /**
     * Autentica o token inicializando a base informada pelo parâmetro "tenant"
     */
    public function authenticate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'tenant' => 'required|string', // Exige o slug do tenant na requisição
        ]);

        // 1. Localiza o Tenant informado
        $tenant = Tenant::where('slug', $validated['tenant'])->where('status', 'active')->first();

        if (!$tenant) {
            return response()->json(['error' => 'Oficina inválida ou inativa.'], 400);
        }

        // 2. Inicializa o banco de dados dessa oficina
        tenancy()->initialize($tenant);

        // 3. Valida o token e autentica o cliente
        $customer = $this->customerPortalService->authenticate($validated['token']);

        if (!$customer) {
            return response()->json([
                'error' => 'Token inválido ou expirado.'
            ], 401);
        }

        // 4. Gera o token Sanctum na base de dados do Tenant
        $tokenResult = $customer->createToken('customer-portal-token');

        return response()->json([
            'message' => 'Autenticado com sucesso.',
            'access_token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
            'customer' => $customer,
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
            ]
        ]);
    }
}
