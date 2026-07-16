<?php

namespace App\DTOs\Tenant;

class CreateDocumentDTO
{
    public function __construct(
        public int $branch_id,
        public string $documentable_type,
        public string $documentable_id,
        public string $title,
        public string $document_type,
        public ?string $description = null,
        public ?string $expires_at = null,
        public array $metadata = []
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            branch_id: (int) $data['branch_id'],
            documentable_type: $data['documentable_type'],
            documentable_id: (string) $data['documentable_id'],
            title: $data['title'],
            document_type: $data['document_type'],
            description: $data['description'] ?? null,
            expires_at: $data['expires_at'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }
}
