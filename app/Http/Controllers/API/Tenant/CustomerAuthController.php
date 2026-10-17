<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\CustomerPortalService;
use App\Models\Master\Tenant;
use App\Models\Tenant\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerAuthController extends Controller
{
    public function __construct(
        protected CustomerPortalService $customerPortalService
    ) {}

    /**
     * Solicita um Magic Link para o portal do cliente.
     *
     * A resposta é SEMPRE a mesma, independente de o e-mail existir ou não,
     * para evitar enumeração de e-mails (email enumeration).
     *
     * O token NÃO é devolvido nesta resposta; é enviado apenas pelo canal
     * cadastrado do cliente (e-mail ou WhatsApp).
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'  => 'required|email|max:254',
            'tenant' => 'required|string|max:100', // slug da oficina
        ]);

        $tenant = Tenant::where('slug', $validated['tenant'])
            ->where('status', 'active')
            ->first();

        // Resposta genérica – não revela se o e-mail ou a oficina existem
        $genericResponse = response()->json([
            'message' => 'Se este e-mail estiver cadastrado, você receberá um link de acesso em breve.',
        ]);

        if (!$tenant) {
            return $genericResponse;
        }

        try {
            tenancy()->initialize($tenant);

            $customer = Customer::where('email', $validated['email'])
                ->where('status', Customer::STATUS_ACTIVE)
                ->first();

            if (!$customer) {
                return $genericResponse;
            }

            // Gera o token e armazena apenas o hash no banco
            $rawToken = $this->customerPortalService->generateMagicLink($validated['email']);

            // TODO: Enviar $rawToken por e-mail / WhatsApp ao cliente cadastrado.
            // Exemplo: $customer->notify(new MagicLinkNotification($rawToken, $tenant->slug));
            // O token NUNCA deve ser devolvido nesta resposta.
            Log::info('Magic link gerado para cliente', [
                'tenant' => $tenant->slug,
                'email'  => $validated['email'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar magic link', [
                'tenant' => $tenant->slug ?? null,
                'error'  => $e->getMessage(),
            ]);
        }

        return $genericResponse;
    }

    /**
     * Autentica o token do Magic Link e emite o token Sanctum do portal.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'  => 'required|string|min:32',
            'tenant' => 'required|string|max:100',
        ]);

        $tenant = Tenant::where('slug', $validated['tenant'])->where('status', 'active')->first();

        if (!$tenant) {
            return response()->json(['error' => 'Link inválido ou expirado.'], 401);
        }

        tenancy()->initialize($tenant);

        $customer = $this->customerPortalService->authenticate($validated['token']);

        if (!$customer) {
            return response()->json([
                'error' => 'Link inválido ou expirado.',
            ], 401);
        }

        $tokenResult = $customer->createToken('customer-portal-token', ['customer'], now()->addHours(8));

        return response()->json([
            'message'      => 'Autenticado com sucesso.',
            'access_token' => $tokenResult->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => 8 * 3600,
            'customer'     => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
            ],
        ]);
    }
}
