<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    /**
     * List audit logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        return response()->json($logs);
    }
}
