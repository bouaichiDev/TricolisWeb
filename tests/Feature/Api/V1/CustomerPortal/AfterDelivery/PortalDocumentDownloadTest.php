<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Documents\Models\Document;
use App\Shared\Database\MorphMap;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalAfterDelivery;
use Tests\Support\CustomerPortalOrders;

/**
 * Téléchargement sécurisé et pièces jointes de réclamation (§15, §23, §30).
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->order = CustomerPortalOrders::order('ikea');
    $this->headers = CustomerPortal::headers('admin@ikea.test');
});

it('streams the file with its MIME type and a safe name', function (): void {
    $document = CustomerPortalAfterDelivery::document($this->order, MorphMap::ORDER, $this->order->id);
    $document->update(['file_name' => '../../etc/passwd.pdf']);

    $response = $this->withHeaders($this->headers)->get("/api/v1/customer/documents/{$document->id}/download")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->not->toContain('/')
        ->and($response->streamedContent())->toBe('contenu');
});

it('answers a controlled 404 when the file is missing or the path climbs the tree', function (): void {
    $missing = CustomerPortalAfterDelivery::document($this->order, MorphMap::ORDER, $this->order->id);
    Storage::disk('local')->delete($missing->storage_path);
    $climbing = CustomerPortalAfterDelivery::document($this->order, MorphMap::ORDER, $this->order->id);
    $climbing->update(['storage_path' => '../.env']);

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/documents/{$missing->id}/download")->assertNotFound();
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/documents/{$climbing->id}/download")->assertNotFound();
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/orders/{$this->order->id}/documents/{$missing->id}/download")->assertNotFound();
});

it('refuses a download without authentication', function (): void {
    $document = CustomerPortalAfterDelivery::document($this->order, MorphMap::ORDER, $this->order->id);

    $this->getJson("/api/v1/customer/documents/{$document->id}/download")->assertUnauthorized();
});

it('attaches a file to its own open claim, then lists and serves it', function (): void {
    $claim = CustomerPortalAfterDelivery::claim('ikea');

    $id = $this->withHeaders($this->headers)
        ->post("/api/v1/customer/claims/{$claim->id}/documents", ['file' => UploadedFile::fake()->image('carton.jpg')], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.documentType', 'claim_evidence')->json('data.id');

    $document = Document::findOrFail($id);

    expect($document->created_by)->toBe(CustomerPortal::member('admin@ikea.test')->user_id)
        ->and($document->organization_id)->toBe($claim->organization_id)
        ->and($document->links()->where('entity_type', MorphMap::CLAIM)->where('entity_id', $claim->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'customer_portal.claim_document_uploaded')->exists())->toBeTrue();

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/claims/{$claim->id}/documents")->assertOk()->assertJsonPath('data.0.id', $id);
    $this->withHeaders($this->headers)->get("/api/v1/customer/documents/{$id}/download")->assertOk();
});

it('refuses a dangerous file, a closed claim and the claim of another customer', function (): void {
    $claim = CustomerPortalAfterDelivery::claim('ikea');
    $upload = fn (string $id, UploadedFile $file) => $this->withHeaders($this->headers)
        ->post("/api/v1/customer/claims/{$id}/documents", ['file' => $file], ['Accept' => 'application/json']);

    $upload($claim->id, UploadedFile::fake()->create('shell.php', 1, 'application/x-php'))->assertUnprocessable()->assertJsonValidationErrors('file');
    $upload(CustomerPortalAfterDelivery::claim('qoqa')->id, UploadedFile::fake()->image('a.jpg'))->assertNotFound();

    $claim->update(['closed_at' => now()]);
    $upload($claim->id, UploadedFile::fake()->image('a.jpg'))->assertStatus(409);

    expect(Document::where('document_type', 'claim_evidence')->count())->toBe(0);
});
