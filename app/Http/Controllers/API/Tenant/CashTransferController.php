<?php

namespace App\Http\Controllers\API\Tenant;

use App\Enums\Tenant\CashTransferType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\CashSession;
use App\Models\Tenant\CashTransfer;
use App\Services\Tenant\CashTransferService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashTransferController extends Controller
{
    public function __construct(
        private CashTransferService $cashTransferService
    ) {}

    /**
     * GET /api/cash/transfers - Lista histórico de transferências com filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CashTransfer::with([
            'sourceSession.cashRegister',
            'sourceSession.user',
            'destinationSession.cashRegister',
            'requestedByUser',
            'processedByUser',
        ])->latest('requested_at');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('session_id')) {
            $sessionId = $request->query('session_id');
            $query->where(function ($q) use ($sessionId) {
                $q->where('source_session_id', $sessionId)
                    ->orWhere('destination_session_id', $sessionId);
            });
        }

        return response()->json($query->paginate(20));
    }

    /**
     * POST /api/cash/transfers - Solicita nova sangria ou transferência (Status: PENDING).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_session_id' => 'required|uuid|exists:cash_sessions,id',
            'destination_session_id' => 'required|uuid|exists:cash_sessions,id',
            'amount' => 'required|numeric|min:0.01',
            'transfer_type' => ['required', Rule::enum(CashTransferType::class)],
            'notes' => 'nullable|string|max:500',
        ]);

        $source = CashSession::findOrFail($validated['source_session_id']);
        $destination = CashSession::findOrFail($validated['destination_session_id']);
        $userId = auth()->id() ?? 1;

        try {
            $transfer = $this->cashTransferService->requestTransfer(
                $source,
                $destination,
                (float) $validated['amount'],
                CashTransferType::from($validated['transfer_type']),
                $validated['notes'] ?? null,
                $userId
            );

            return response()->json([
                'message' => 'Transferência solicitada com sucesso. Aguardando aceite.',
                'transfer' => $transfer,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/cash/transfers/{id}/accept - O destinatário dá o Aceite formal (Duplo Aceite).
     */
    public function accept(Request $request, string $id): JsonResponse
    {
        $transfer = CashTransfer::findOrFail($id);
        $userId = auth()->id() ?? 1;
        $notes = $request->input('notes');

        try {
            $processed = $this->cashTransferService->acceptTransfer($transfer, $userId, $notes);

            return response()->json([
                'message' => 'Transferência aceita e valores creditados com sucesso.',
                'transfer' => $processed,
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/cash/transfers/{id}/reject - O destinatário rejeita/contesta a transferência.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $transfer = CashTransfer::findOrFail($id);
        $request->validate(['reason' => 'required|string|max:500']);
        $userId = auth()->id() ?? 1;

        try {
            $processed = $this->cashTransferService->rejectTransfer(
                $transfer,
                $request->input('reason'),
                $userId
            );

            return response()->json([
                'message' => 'Transferência rejeitada com sucesso.',
                'transfer' => $processed,
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/cash/transfers/pending-incoming/{destinationSessionId} - Fila de sangrias pendentes de aceite.
     */
    public function pendingIncoming(string $destinationSessionId): JsonResponse
    {
        $pending = $this->cashTransferService->getPendingIncomingTransfers($destinationSessionId);
        return response()->json($pending);
    }
}
