<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\CustomerPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    public function __construct(
        protected CustomerPortalService $customerPortalService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $token = $this->customerPortalService->generateMagicLink($validated['email']);
            $tenant = tenant();

            return response()->json([
                'message' => 'Magic Link gerado com sucesso.',
                'token' => $token,
                'login_url' => "http://{$tenant->slug}.prime-erp.local/api/customer/authenticate?token={$token}",
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Nenhum cliente ativo localizado com este e-mail.'
            ], 404);
        }
    }

    public function authenticate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $customer = $this->customerPortalService->authenticate($validated['token']);

        if (!$customer) {
            return response()->json([
                'error' => 'Token inválido ou expirado.'
            ], 401);
        }

        // Generate Sanctum token for Customer model
        $tokenResult = $customer->createToken('customer-portal-token');

        return response()->json([
            'message' => 'Autenticado com sucesso.',
            'access_token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
            'customer' => $customer,
        ]);
    }
}
