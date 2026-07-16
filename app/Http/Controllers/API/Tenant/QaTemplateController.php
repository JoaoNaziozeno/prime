<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\QaTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QaTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', QaTemplate::class);

        $templates = QaTemplate::paginate(20);

        return response()->json($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', QaTemplate::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array',
            'items.*' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $template = QaTemplate::create($validated);

        return response()->json($template, 201);
    }

    public function show(QaTemplate $qaTemplate): JsonResponse
    {
        $this->authorize('view', $qaTemplate);

        return response()->json($qaTemplate);
    }

    public function update(Request $request, QaTemplate $qaTemplate): JsonResponse
    {
        $this->authorize('update', $qaTemplate);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array',
            'items.*' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $qaTemplate->update($validated);

        return response()->json($qaTemplate);
    }

    public function destroy(QaTemplate $qaTemplate): JsonResponse
    {
        $this->authorize('delete', $qaTemplate);

        $qaTemplate->delete();

        return response()->json(null, 204);
    }
}
