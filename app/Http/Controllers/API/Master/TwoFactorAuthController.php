<?php

namespace App\Http\Controllers\API\Master;

use App\Http\Controllers\Controller;
use App\Services\Master\TotpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TwoFactorAuthController extends Controller
{
    public function __construct(
        protected TotpService $totpService
    ) {}

    /**
     * Enable two-factor authentication (generate secret & recovery codes)
     */
    public function enable(Request $request): JsonResponse
    {
        /** @var \App\Models\Master\User $user */
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $secret = $this->totpService->generateSecret();
        $recoveryCodes = $this->totpService->generateRecoveryCodes();

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => null, // Needs confirmation
        ]);

        $qrCodeUrl = $this->totpService->getQrCodeUrl($user->email, $secret);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes,
        ], 200);
    }

    /**
     * Confirm two-factor authentication
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        /** @var \App\Models\Master\User $user */
        $user = auth()->user();

        if (!$user || !$user->two_factor_secret) {
            return response()->json(['message' => '2FA not enabled or unauthenticated.'], 400);
        }

        $isValid = $this->totpService->verifyCode($user->two_factor_secret, $request->code);

        if (!$isValid) {
            return response()->json(['message' => 'Código de verificação inválido.'], 422);
        }

        $user->update([
            'two_factor_confirmed_at' => now(),
        ]);

        return response()->json(['message' => 'Autenticação de dois fatores confirmada com sucesso.']);
    }

    /**
     * Disable two-factor authentication
     */
    public function disable(Request $request): JsonResponse
    {
        /** @var \App\Models\Master\User $user */
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json(['message' => 'Autenticação de dois fatores desativada com sucesso.']);
    }
}
