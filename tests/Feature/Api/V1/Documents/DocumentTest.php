<?php

use App\Modules\Documents\Models\Document;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    Storage::fake('local');
});

it('uploads, downloads and deletes an organization document', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->post('/api/v1/documents', [
        'file' => UploadedFile::fake()->create('preuve.pdf', 100, 'application/pdf'),
        'documentType' => 'proof', 'status' => 'active', 'entityType' => 'organization', 'entityId' => $this->organization->id,
    ]);
    $response->assertCreated()->assertJsonPath('data.fileName', 'preuve.pdf')->assertJsonMissingPath('data.storagePath');
    $document = Document::findOrFail($response->json('data.id'));
    Storage::disk('local')->assertExists($document->storage_path);
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->get("/api/v1/documents/$document->id/download")->assertOk();
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->deleteJson("/api/v1/documents/$document->id")->assertNoContent();
    $this->assertSoftDeleted('documents', ['id' => $document->id]);
    Storage::disk('local')->assertExists($document->storage_path);
});

it('purges a deleted document only after the retention period', function (): void {
    $document = Document::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);
    Storage::disk('local')->put($document->storage_path, 'contenu');
    $document->delete();

    $this->artisan('documents:purge', ['--days' => 30])->assertSuccessful();
    Storage::disk('local')->assertExists($document->storage_path);
    $this->assertDatabaseHas('documents', ['id' => $document->id]);

    $document->forceFill(['deleted_at' => now()->subDays(45)])->saveQuietly();
    $this->artisan('documents:purge', ['--days' => 30])->assertSuccessful();
    Storage::disk('local')->assertMissing($document->storage_path);
    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
});

it('hides a deleted document from listing and download', function (): void {
    $document = Document::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);
    $document->delete();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->get("/api/v1/documents/$document->id/download")->assertNotFound();
});

it('does not expose a document from another organization', function (): void {
    $other = Organization::factory()->create();
    $document = Document::create(['organization_id' => $other->id, 'document_type' => 'proof', 'status' => 'active', 'file_name' => 'x.pdf', 'storage_path' => 'documents/x.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'created_by' => $this->user->id]);
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->getJson("/api/v1/documents/$document->id")->assertNotFound();
});

/**
 * Une preuve de livraison ne s'efface pas.
 *
 * Elle est deposee par le chauffeur et fait foi : la supprimer detruirait la
 * seule trace de ce qui a ete remis. Une preuve erronee se conteste par une
 * reclamation.
 */
it('refuses to delete a proof of delivery, whatever the permission', function (): void {
    $created = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->post('/api/v1/documents', [
        'file' => UploadedFile::fake()->create('pod-signature.pdf', 50, 'application/pdf'),
        'documentType' => 'pod', 'status' => 'active',
        'entityType' => 'organization', 'entityId' => $this->organization->id,
    ])->assertCreated()->json('data.id');

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->deleteJson("/api/v1/documents/$created")
        ->assertForbidden();

    $this->assertDatabaseHas('documents', ['id' => $created, 'deleted_at' => null]);

    // Elle reste consultable et telechargeable.
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->get("/api/v1/documents/$created/download")->assertOk();
});

/** Un vocal decrit un dommage en trente secondes la ou personne n'ecrirait. */
it('accepts an audio file as a document', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->post('/api/v1/documents', [
        'file' => UploadedFile::fake()->create('temoignage.mp3', 200, 'audio/mpeg'),
        'documentType' => 'claim_evidence', 'status' => 'active',
        'entityType' => 'organization', 'entityId' => $this->organization->id,
    ])->assertCreated()->assertJsonPath('data.fileName', 'temoignage.mp3');
});
