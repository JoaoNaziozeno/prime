<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Invoice;
use App\Services\Tenant\ComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function __construct(
        protected ComplianceService $complianceService
    ) {}

    public function anonymizeCustomer(Customer $customer, Request $request): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Master\User) {
            return response()->json(['error' => 'Acesso restrito a funcionários.'], 403);
        }

        $this->complianceService->anonymizeCustomer($customer);

        return response()->json([
            'message' => 'Dados pessoais do cliente anonimizados com sucesso em conformidade com a LGPD.',
        ]);
    }

    public function anonymizeDriver(Driver $driver, Request $request): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Master\User) {
            return response()->json(['error' => 'Acesso restrito a funcionários.'], 403);
        }

        $this->complianceService->anonymizeDriver($driver);

        return response()->json([
            'message' => 'Dados pessoais do motorista anonimizados com sucesso em conformidade com a LGPD.',
        ]);
    }

    public function purgeLogs(Request $request): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Master\User || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso restrito a administradores do sistema.'], 403);
        }

        $validated = $request->validate([
            'retention_months' => 'required|integer|min:1',
        ]);

        $deletedCount = $this->complianceService->purgeAuditLogs($validated['retention_months']);

        return response()->json([
            'message' => 'Purge de logs concluído.',
            'deleted_records_count' => $deletedCount,
        ]);
    }

    public function transmitNfe(Invoice $invoice, Request $request): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Master\User) {
            return response()->json(['error' => 'Acesso restrito a funcionários.'], 403);
        }

        if ($invoice->status !== Invoice::STATUS_PAID) {
            return response()->json(['error' => 'Apenas faturas pagas podem ter NF-e emitida.'], 400);
        }

        $result = $this->complianceService->transmitInvoiceToNfe($invoice);

        return response()->json([
            'message' => 'NF-e emitida e transmitida com sucesso para o fisco.',
            'nfe_details' => $result,
        ]);
    }
}
