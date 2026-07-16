<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\ReportBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiDashboardController extends Controller
{
    public function __construct(
        protected ReportBuilderService $reportBuilderService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Master\User) {
            return response()->json(['error' => 'Acesso restrito a funcionários.'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $biData = $this->reportBuilderService->getBiDataRange($validated['start_date'], $validated['end_date']);

        return response()->json($biData);
    }

    public function triggerSnapshot(Request $request): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Master\User) {
            return response()->json(['error' => 'Acesso restrito a funcionários.'], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $snapshot = $this->reportBuilderService->generateDailySnapshot($validated['date']);

        return response()->json([
            'message' => 'Snapshot diário gerado com sucesso.',
            'snapshot' => $snapshot,
        ]);
    }
}
