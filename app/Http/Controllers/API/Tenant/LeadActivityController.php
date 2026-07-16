<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Lead;
use App\Models\Tenant\LeadActivity;
use App\Services\Tenant\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadActivityController extends Controller
{
    protected CrmService $crmService;

    public function __construct(CrmService $crmService)
    {
        $this->crmService = $crmService;
    }

    /**
     * Display a listing of activities for the specified lead.
     */
    public function index(Lead $lead): JsonResponse
    {
        return response()->json($lead->activities);
    }

    /**
     * Store a newly created activity for a lead.
     */
    public function store(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|in:call,meeting,email,task,note',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date_format:Y-m-d H:i:s',
            'completed_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        $activity = $this->crmService->createActivity($lead, $data, (string) $request->user()->id);

        return response()->json($activity, 201);
    }

    /**
     * Mark an activity as completed.
     */
    public function complete(Request $request, LeadActivity $activity): JsonResponse
    {
        $updatedActivity = $this->crmService->completeActivity($activity);

        return response()->json([
            'message' => 'Atividade marcada como concluída.',
            'activity' => $updatedActivity,
        ]);
    }
}
