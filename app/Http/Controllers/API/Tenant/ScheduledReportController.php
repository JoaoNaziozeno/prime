<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ScheduledReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduledReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScheduledReport::class);

        $jobs = ScheduledReport::with('customReport')->paginate(20);

        return response()->json($jobs);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ScheduledReport::class);

        $validated = $request->validate([
            'custom_report_id' => 'required|exists:tenant.custom_reports,id',
            'frequency' => 'required|string|in:daily,weekly,monthly',
            'email_recipient' => 'required|email|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['user_id'] = (string) $request->user()->id;

        $job = ScheduledReport::create($validated);

        return response()->json($job, 201);
    }

    public function show(ScheduledReport $scheduledReport): JsonResponse
    {
        $this->authorize('view', $scheduledReport);

        return response()->json($scheduledReport->load('customReport'));
    }

    public function update(Request $request, ScheduledReport $scheduledReport): JsonResponse
    {
        $this->authorize('update', $scheduledReport);

        $validated = $request->validate([
            'custom_report_id' => 'required|exists:tenant.custom_reports,id',
            'frequency' => 'required|string|in:daily,weekly,monthly',
            'email_recipient' => 'required|email|max:255',
            'is_active' => 'boolean',
        ]);

        $scheduledReport->update($validated);

        return response()->json($scheduledReport);
    }

    public function destroy(ScheduledReport $scheduledReport): JsonResponse
    {
        $this->authorize('delete', $scheduledReport);

        $scheduledReport->delete();

        return response()->json(null, 204);
    }
}
