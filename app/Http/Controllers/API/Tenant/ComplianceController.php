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

    /**
     * Anonimização é irreversível — exige administrador.
     */
    public function anonymizeCustomer(Customer $customer, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof \App\Models\Master\User || !$user->isAdmin()) {
            return response()->json(['error' => 'Apenas administradores podem anonimizar dados pessoais.'], 403);
        }

        $this->complianceService->anonymizeCustomer($customer);

        return response()->json([
            'message' => 'Dados pessoais do cliente anonimizados com sucesso em conformidade com a LGPD.',
        ]);
    }

    /**
     * Anonimização é irreversível — exige administrador.
     */
    public function anonymizeDriver(Driver $driver, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof \App\Models\Master\User || !$user->isAdmin()) {
            return response()->json(['error' => 'Apenas administradores podem anonimizar dados pessoais.'], 403);
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
            'retention_months' => 'required|integer|min:12',
        ]);

        $deletedCount = $this->complianceService->purgeAuditLogs($validated['retention_months']);

        return response()->json([
            'message'               => 'Purge de logs concluído.',
            'deleted_records_count' => $deletedCount,
        ]);
    }

    /**
     * NF-e — funcionalidade não implementada.
     * Retorna 501 Not Implemented para não enganar usuários.
     */
    public function transmitNfe(Invoice $invoice, Request $request): JsonResponse
    {
        return response()->json([
            'error'   => 'Emissão de NF-e não implementada.',
            'message' => 'A integração com o emissor fiscal ainda não foi configurada. Entre em contato com o suporte para habilitar esta funcionalidade.',
        ], 501);
    }
}
