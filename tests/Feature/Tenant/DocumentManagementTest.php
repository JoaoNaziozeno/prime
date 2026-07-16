<?php

use App\Models\Tenant\Document;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\Driver;
use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Services\Tenant\DocumentService;
use App\DTOs\Tenant\CreateDocumentDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

describe('Document Management Logic & Storage', function () {

    beforeEach(function () {
        Storage::fake('local');

        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'documents-logic-tenant',
        ]);
        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        $this->driver = Driver::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);

        $this->documentService = app(DocumentService::class);
        $this->userId = fake()->uuid();
    });

    test('uploads and stores document physically and in database', function () {
        $file = UploadedFile::fake()->create('cnh_copy.pdf', 500, 'application/pdf');

        $dto = new CreateDocumentDTO(
            branch_id: $this->branch->id,
            documentable_type: Driver::class,
            documentable_id: (string) $this->driver->id,
            title: 'CNH do Motorista',
            document_type: 'cnh',
            expires_at: now()->addYear()->toDateString(),
        );

        $document = $this->documentService->store($dto, $file, $this->userId);

        expect($document)->toBeInstanceOf(Document::class);
        expect($document->file_name)->toBe('cnh_copy.pdf');
        
        // Assert file exists on virtual local disk
        Storage::disk('local')->assertExists($document->file_path);
    });

    test('prevents uploading invalid file formats like php files', function () {
        $file = UploadedFile::fake()->create('script.php', 10, 'text/php');

        $dto = new CreateDocumentDTO(
            branch_id: $this->branch->id,
            documentable_type: Driver::class,
            documentable_id: (string) $this->driver->id,
            title: 'Hacker Script',
            document_type: 'malicious',
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Tipo de arquivo não permitido.');
        
        $this->documentService->store($dto, $file, $this->userId);
    });

    test('prevents uploading files exceeding 10MB limit', function () {
        // 11 MB file
        $file = UploadedFile::fake()->create('big_file.zip', 11 * 1024, 'application/zip');

        $dto = new CreateDocumentDTO(
            branch_id: $this->branch->id,
            documentable_type: Vehicle::class,
            documentable_id: (string) $this->vehicle->id,
            title: 'Big Backup',
            document_type: 'backup',
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O arquivo excede o limite de tamanho permitido de 10 MB.');

        $this->documentService->store($dto, $file, $this->userId);
    });

    test('deletes physical file upon document removal', function () {
        $file = UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf');
        $dto = new CreateDocumentDTO(
            branch_id: $this->branch->id,
            documentable_type: Vehicle::class,
            documentable_id: (string) $this->vehicle->id,
            title: 'Contrato Leasing',
            document_type: 'contract',
        );

        $document = $this->documentService->store($dto, $file, $this->userId);
        Storage::disk('local')->assertExists($document->file_path);

        $this->documentService->delete($document);

        // Assert file deleted physically
        Storage::disk('local')->assertMissing($document->file_path);
        // Assert record is hard deleted from DB
        expect(Document::find($document->id))->toBeNull();
    });

    test('expiring scopes query correct expiration status', function () {
        // Expired document
        Document::factory()->expired()->create([
            'branch_id' => $this->branch->id,
        ]);

        // Expiring soon (10 days)
        Document::factory()->expiringSoon()->create([
            'branch_id' => $this->branch->id,
        ]);

        // Long expiration
        Document::factory()->create([
            'branch_id' => $this->branch->id,
            'expires_at' => now()->addDays(60),
        ]);

        expect(Document::expired()->count())->toBe(1);
        expect(Document::expiringWithin(30)->count())->toBe(1);
    });

});

describe('Document Management API Routes & Policies', function () {

    beforeEach(function () {
        Storage::fake('local');

        $this->user = User::factory()->superAdmin()->create();
        $this->regularUser = User::factory()->create(['role' => 'user']);
        
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'documents-api-tenant',
        ]);
        
        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);
    });

    test('can upload document via API', function () {
        $url = "http://documents-api-tenant.prime-erp.local/api/documents";

        $response = $this->actingAs($this->user)->postJson($url, [
            'branch_id' => $this->branch->id,
            'documentable_type' => Vehicle::class,
            'documentable_id' => (string) $this->vehicle->id,
            'title' => 'Foto Vistoria Frontal',
            'document_type' => 'plate_photo',
            'file' => UploadedFile::fake()->create('front.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'title' => 'Foto Vistoria Frontal',
            'file_name' => 'front.jpg',
        ]);
    });

    test('can download uploaded document via API route', function () {
        // Store a document first
        $file = UploadedFile::fake()->create('test_doc.pdf', 100, 'application/pdf');
        $dto = new CreateDocumentDTO(
            branch_id: $this->branch->id,
            documentable_type: Vehicle::class,
            documentable_id: (string) $this->vehicle->id,
            title: 'Test Doc',
            document_type: 'cnh',
        );
        $document = app(DocumentService::class)->store($dto, $file, $this->user->id);

        $url = "http://documents-api-tenant.prime-erp.local/api/documents/{$document->id}/download";

        $response = $this->actingAs($this->user)->get($url);

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=test_doc.pdf');
    });

    test('regular user cannot delete documents due to DocumentPolicy restriction', function () {
        $document = Document::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $url = "http://documents-api-tenant.prime-erp.local/api/documents/{$document->id}";

        $response = $this->actingAs($this->regularUser)->deleteJson($url);

        $response->assertStatus(403);
    });

    test('admin user can delete documents successfully', function () {
        $document = Document::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $url = "http://documents-api-tenant.prime-erp.local/api/documents/{$document->id}";

        $response = $this->actingAs($this->user)->deleteJson($url);

        $response->assertStatus(204);
    });

});
