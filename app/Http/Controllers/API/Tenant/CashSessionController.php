<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CashSession;
use App\Models\Tenant\OrderOfService;
use App\Services\Tenant\CashMovementService;
use App\Services\Tenant\CashSessionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashSessionController extends Controller
{
    public function __construct(
        private CashSessionService $cashSessionService,
        private CashMovementService $cashMovementService
    ) {}

    /**
     * GET /api/cash/session/current - Obtém a sessão ativa do usuário atual com resumo.
     */
    public function current(): JsonResponse
    {
        $userId = auth()->id() ?? 1;
        $session = $this->cashSessionService->getCurrentSession($userId);

        if (!$session) {
            return response()->json([
                'has_active_session' => false,
                'session' => null,
            ]);
        }

        $summary = $this->cashSessionService->getSessionSummary($session);

        return response()->json([
            'has_active_session' => true,
            'session' => $session,
            'summary' => $summary,
        ]);
    }

    /**
     * GET /api/cash/session/central - Obtém a sessão ativa do Caixa Central (para ADM).
     */
    public function central(): JsonResponse
    {
        $session = $this->cashSessionService->getCentralSession();

        if (!$session) {
            return response()->json([
                'has_active_session' => false,
                'session' => null,
            ]);
        }

        $summary = $this->cashSessionService->getSessionSummary($session);

        return response()->json([
            'has_active_session' => true,
            'session' => $session,
            'summary' => $summary,
        ]);
    }

    /**
     * POST /api/cash/session/open - Abre uma sessão de caixa.
     */
    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cash_register_id' => 'required|uuid|exists:cash_registers,id',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        $userId = auth()->id() ?? 1;

        try {
            $session = $this->cashSessionService->openSession(
                $validated['cash_register_id'],
                (float) $validated['opening_balance'],
                $userId
            );

            return response()->json([
                'message' => 'Sessão de caixa aberta com sucesso.',
                'session' => $session,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/cash/session/{id}/close - Fechamento Cego de Caixa.
     */
    public function close(Request $request, string $id): JsonResponse
    {
        $session = CashSession::findOrFail($id);

        $validated = $request->validate([
            'reported_cash_balance' => 'required|numeric|min:0',
            'denominations' => 'nullable|array',
            'denominations.*.denomination' => 'required_with:denominations|numeric',
            'denominations.*.quantity' => 'required_with:denominations|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $userId = auth()->id() ?? 1;

        try {
            $closedSession = $this->cashSessionService->closeSessionBlind(
                $session,
                (float) $validated['reported_cash_balance'],
                $validated['denominations'] ?? [],
                $validated['notes'] ?? null,
                $userId
            );

            return response()->json([
                'message' => 'Caixa encerrado com sucesso.',
                'session' => $closedSession,
                'difference' => $closedSession->cash_difference,
                'is_contested' => $closedSession->status->value === 'contested',
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/cash/session/{id}/movements - Extrato de movimentações da sessão.
     */
    public function movements(string $id): JsonResponse
    {
        $session = CashSession::findOrFail($id);
        $movements = $session->movements()
            ->with(['paymentMethod', 'orderOfService.customer', 'createdByUser'])
            ->latest('created_at')
            ->get();

        return response()->json($movements);
    }

    /**
     * POST /api/cash/session/{id}/receive-os - Recebimento de O.S com Split de Pagamentos.
     */
    public function receiveOrderPayment(Request $request, string $id): JsonResponse
    {
        $session = CashSession::findOrFail($id);

        $validated = $request->validate([
            'order_of_service_id' => 'required|uuid|exists:orders_of_service,id',
            'splits' => 'required|array|min:1',
            'splits.*.payment_method_id' => 'required|uuid|exists:payment_methods,id',
            'splits.*.amount' => 'required|numeric|min:0.01',
            'splits.*.metadata' => 'nullable|array',
        ]);

        $order = OrderOfService::with('customer')->findOrFail($validated['order_of_service_id']);
        $userId = auth()->id() ?? 1;

        try {
            $result = $this->cashMovementService->receiveOrderOfServicePayment(
                $session,
                $order,
                $validated['splits'],
                $userId
            );

            return response()->json([
                'message' => 'Pagamento de O.S registrado com sucesso.',
                'result' => $result,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/cash/session/{id}/petty-expense - Lançamento de despesa miúda de balcão.
     */
    public function pettyExpense(Request $request, string $id): JsonResponse
    {
        $session = CashSession::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'receipt_number' => 'nullable|string|max:50',
        ]);

        $userId = auth()->id() ?? 1;

        try {
            $movement = $this->cashMovementService->registerPettyExpense(
                $session,
                (float) $validated['amount'],
                $validated['description'],
                $validated['receipt_number'] ?? null,
                $userId
            );

            return response()->json([
                'message' => 'Despesa registrada com sucesso.',
                'movement' => $movement,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/cash/session/{id}/supply - Lançamento de suprimento avulso.
     */
    public function supply(Request $request, string $id): JsonResponse
    {
        $session = CashSession::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
        ]);

        $userId = auth()->id() ?? 1;

        try {
            $movement = $this->cashMovementService->registerSupply(
                $session,
                (float) $validated['amount'],
                $validated['description'],
                $userId
            );

            return response()->json([
                'message' => 'Suprimento registrado com sucesso.',
                'movement' => $movement,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
