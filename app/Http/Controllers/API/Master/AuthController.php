<?php

namespace App\Http\Controllers\API\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\User;
use App\Services\Master\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        protected TotpService $totpService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email ou senha incorretos.',
            ], 401);
        }

        if (!$user->isActive()) {
            return response()->json([
                'message' => 'Esta conta de usuário está desativada.',
            ], 401);
        }

        // Enforce two-factor authentication when it is enabled and confirmed.
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $code = $request->input('two_factor_code');
            $recoveryCode = $request->input('recovery_code');

            if (!$code && !$recoveryCode) {
                return response()->json([
                    'message' => 'Código de autenticação em dois fatores necessário.',
                    'two_factor_required' => true,
                ], 422);
            }

            $valid = $code
                ? $this->totpService->verifyCode($user->two_factor_secret, (string) $code)
                : $this->consumeRecoveryCode($user, (string) $recoveryCode);

            if (!$valid) {
                return response()->json([
                    'message' => 'Código de autenticação inválido.',
                    'two_factor_required' => true,
                ], 401);
            }
        }

        // Registrar auditoria de login
        $user->recordLogin($request->ip());

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],

            'tenant' => $user->company && $user->company->tenant ? [
                'id' => $user->company->tenant->id,
                'slug' => $user->company->tenant->slug,
                'name' => $user->company->name,
            ] : null
        ]);
    }

    /**
     * Validate a recovery code and remove it so it can only be used once.
     */
    private function consumeRecoveryCode(User $user, string $input): bool
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        try {
            $codes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?: [];
        } catch (\Throwable $e) {
            return false;
        }

        $input = trim($input);

        foreach ($codes as $index => $code) {
            if (hash_equals((string) $code, $input)) {
                unset($codes[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes))),
                ])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Logout: revoga o token atual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada com sucesso.']);
    }

    /**
     * Troca de senha pelo próprio usuário.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Senha atual incorreta.'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Revoga todos os tokens exceto o atual, forçando relogin em outros dispositivos
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json(['message' => 'Senha alterada com sucesso. Outros dispositivos foram desconectados.']);
    }
}