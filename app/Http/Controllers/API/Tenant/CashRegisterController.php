<?php

namespace App\Http\Controllers\API\Tenant;

use App\Enums\Tenant\CashRegisterType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\CashRegister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashRegisterController extends Controller
{
    /**
     * GET /api/cash-registers - Lista todos os caixas cadastrados.
     */
    public function index(): JsonResponse
    {
        $registers = CashRegister::with(['branch', 'activeSession.user'])
            ->orderBy('is_central', 'desc')
            ->orderBy('name')
            ->get()
            ->map(function ($reg) {
                return [
                    'id' => $reg->id,
                    'name' => $reg->name,
                    'type' => $reg->type->value,
                    'type_label' => $reg->type->label(),
                    'is_central' => $reg->is_central,
                    'is_active' => $reg->is_active,
                    'max_cash_limit' => (float) ($reg->max_cash_limit ?? 0),
                    'branch' => $reg->branch ? ['id' => $reg->branch->id, 'name' => $reg->branch->name] : null,
                    'active_session' => $reg->activeSession ? [
                        'id' => $reg->activeSession->id,
                        'opened_at' => $reg->activeSession->opened_at,
                        'opening_balance' => (float) $reg->activeSession->opening_balance,
                        'current_cash_balance' => $reg->activeSession->getCurrentCashBalance(),
                        'operator' => $reg->activeSession->user ? [
                            'id' => $reg->activeSession->user->id,
                            'name' => $reg->activeSession->user->name,
                        ] : null,
                    ] : null,
                ];
            });

        return response()->json($registers);
    }

    /**
     * POST /api/cash-registers - Cria um novo ponto de caixa.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => ['required', Rule::enum(CashRegisterType::class)],
            'is_central' => 'boolean',
            'branch_id' => 'nullable|exists:branches,id',
            'max_cash_limit' => 'nullable|numeric|min:0',
        ]);

        $register = CashRegister::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'is_central' => $validated['is_central'] ?? false,
            'branch_id' => $validated['branch_id'] ?? null,
            'max_cash_limit' => $validated['max_cash_limit'] ?? null,
            'is_active' => true,
        ]);

        return response()->json($register, 201);
    }

    /**
     * PUT /api/cash-registers/{id} - Atualiza um ponto de caixa.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $register = CashRegister::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'is_active' => 'boolean',
            'max_cash_limit' => 'nullable|numeric|min:0',
        ]);

        $register->update($validated);

        return response()->json($register);
    }
}
