<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Services\Tenant\DocumentService;
use App\DTOs\Tenant\CreateDocumentDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService
    ) {}

    /**
     * Display a listing of the documents.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $query = Document::query();

        // Polymorphic filter
        if ($request->has('documentable_type') && $request->has('documentable_id')) {
            $query->where('documentable_type', $request->get('documentable_type'))
                  ->where('documentable_id', $request->get('documentable_id'));
        }

        // Expiration status filters
        if ($request->boolean('expired')) {
            $query->expired();
        } elseif ($request->boolean('expires_soon')) {
            $query->expiringWithin($request->get('days_range', 30));
        }

        if ($request->has('document_type')) {
            $query->byType($request->get('document_type'));
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        return response()->json($documents);
    }

    /**
     * Store a newly created document.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        $data = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'documentable_type' => 'required|string',
            'documentable_id' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_type' => 'required|string|max:100',
            'expires_at' => 'nullable|date',
            'metadata' => 'nullable|array',
            'file' => 'required|file',
        ]);

        $dto = CreateDocumentDTO::fromRequest($data);
        $file = $request->file('file');

        $document = $this->documentService->store(
            $dto,
            $file,
            auth()->id() ?? '00000000-0000-0000-0000-000000000000'
        );

        return response()->json($document, 201);
    }

    /**
     * Display the specified document.
     */
    public function show(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        return response()->json($document);
    }

    /**
     * Download the physical file associated with the document.
     */
    public function download(Document $document)
    {
        $this->authorize('download', $document);

        return $this->documentService->download($document);
    }

    /**
     * Remove the specified document from storage.
     */
    public function destroy(Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $this->documentService->delete($document);

        return response()->json(null, 204);
    }
}
