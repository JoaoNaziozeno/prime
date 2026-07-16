<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Document;
use App\DTOs\Tenant\CreateDocumentDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class DocumentService
{
    protected function getDisk(): string
    {
        return config('filesystems.default', 'local');
    }

    /**
     * Allowed mime types for security
     */
    protected array $allowedMimeTypes = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
        'application/zip',
    ];

    /**
     * Store and upload a new document.
     */
    public function store(CreateDocumentDTO $dto, UploadedFile $file, string $userId): Document
    {
        // 1. Validation
        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw ValidationException::withMessages([
                'file' => 'Tipo de arquivo não permitido. Apenas PDFs, imagens, documentos de texto/Office e arquivos ZIP são aceitos.',
            ]);
        }

        // 10MB Max size limit
        $maxSizeBytes = 10 * 1024 * 1024;
        if ($file->getSize() > $maxSizeBytes) {
            throw ValidationException::withMessages([
                'file' => 'O arquivo excede o limite de tamanho permitido de 10 MB.',
            ]);
        }

        // 2. Storage Partitioning
        $tenantId = tenant('id') ?? 'default';
        $subFolder = "tenants/{$tenantId}/documents/{$dto->document_type}";

        $extension = $file->getClientOriginalExtension();
        $fileName = Str::uuid() . '.' . $extension;

        $filePath = $file->storeAs($subFolder, $fileName, $this->getDisk());

        if (!$filePath) {
            throw new \RuntimeException('Falha ao gravar arquivo no disco.');
        }

        // 3. Database record
        return Document::create([
            'branch_id' => $dto->branch_id,
            'documentable_type' => $dto->documentable_type,
            'documentable_id' => $dto->documentable_id,
            'title' => $dto->title,
            'description' => $dto->description,
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'document_type' => $dto->document_type,
            'expires_at' => $dto->expires_at,
            'metadata' => $dto->metadata,
            'created_by' => $userId,
        ]);
    }

    /**
     * Delete document and physical file.
     */
    public function delete(Document $document): bool
    {
        // Delete physical file
        $disk = $this->getDisk();
        if (Storage::disk($disk)->exists($document->file_path)) {
            Storage::disk($disk)->delete($document->file_path);
        }

        // Hard delete from DB to prevent orphaned DB entries referencing deleted files
        return $document->forceDelete();
    }

    /**
     * Download the physical file.
     */
    public function download(Document $document)
    {
        $disk = $this->getDisk();
        if (!Storage::disk($disk)->exists($document->file_path)) {
            abort(404, 'O arquivo solicitado não foi encontrado no servidor.');
        }

        return Storage::disk($disk)->download($document->file_path, $document->file_name);
    }
}
