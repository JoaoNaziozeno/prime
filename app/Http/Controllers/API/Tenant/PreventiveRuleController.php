<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PreventiveRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreventiveRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PreventiveRule::class);

        $query = PreventiveRule::query();

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->get('vehicle_id'));
        }

        $rules = $query->paginate(20);

        return response()->json($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PreventiveRule::class);

        $validated = $request->validate([
            'vehicle_id' => 'nullable|exists:tenant.vehicles,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'interval_kms' => 'required|integer|min:1',
            'interval_days' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $rule = PreventiveRule::create($validated);

        return response()->json($rule, 201);
    }

    public function show(PreventiveRule $preventiveRule): JsonResponse
    {
        $this->authorize('view', $preventiveRule);

        return response()->json($preventiveRule);
    }

    public function update(Request $request, PreventiveRule $preventiveRule): JsonResponse
    {
        $this->authorize('update', $preventiveRule);

        $validated = $request->validate([
            'vehicle_id' => 'nullable|exists:tenant.vehicles,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'interval_kms' => 'required|integer|min:1',
            'interval_days' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $preventiveRule->update($validated);

        return response()->json($preventiveRule);
    }

    public function destroy(PreventiveRule $preventiveRule): JsonResponse
    {
        $this->authorize('delete', $preventiveRule);

        $preventiveRule->delete();

        return response()->json(null, 204);
    }
}
