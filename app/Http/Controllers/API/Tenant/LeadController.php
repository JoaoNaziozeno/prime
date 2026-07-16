<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Lead;
use App\Services\Tenant\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    protected CrmService $crmService;

    public function __construct(CrmService $crmService)
    {
        $this->crmService = $crmService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        
        $query = Lead::query();

        if ($status) {
            $query->where('status', $status);
        }

        $leads = $query->orderBy('created_at', 'desc')->get();

        return response()->json($leads);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:tenant.branches,id',
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:new,contacted,qualified,proposal_sent,converted,lost',
            'estimated_value' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $lead = $this->crmService->createLead($data, (string) $request->user()->id);

        return response()->json($lead, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Lead $lead): JsonResponse
    {
        return response()->json($lead->load(['activities', 'branch']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:tenant.branches,id',
            'name' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:new,contacted,qualified,proposal_sent,converted,lost',
            'estimated_value' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $updatedLead = $this->crmService->updateLead($lead, $data);

        return response()->json($updatedLead);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return response()->json(['message' => 'Lead removido com sucesso.']);
    }

    /**
     * Convert a Lead into a Customer.
     */
    public function convert(Request $request, Lead $lead): JsonResponse
    {
        $customer = $this->crmService->convert($lead, (string) $request->user()->id);

        return response()->json([
            'message' => 'Lead convertido em cliente com sucesso.',
            'customer' => $customer,
        ]);
    }
}
