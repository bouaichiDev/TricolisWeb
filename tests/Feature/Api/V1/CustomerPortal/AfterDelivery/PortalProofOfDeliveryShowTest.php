<?php

use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalAfterDelivery;
use Tests\Support\CustomerPortalOrders;

/**
 * Détail d'une preuve de livraison : signature et photo par le module
 * Document sécurisé, jamais par un chemin de stockage (§19, §32).
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->order = CustomerPortalOrders::order('ikea');
    $this->headers = CustomerPortal::headers('viewer@ikea.test');
});

it('shows the proof with signature and photo metadata only', function (): void {
    $proof = CustomerPortalAfterDelivery::proof($this->order);

    $response = $this->withHeaders($this->headers)->getJson("/api/v1/customer/proofs-of-delivery/{$proof->id}")->assertOk()
        ->assertJsonPath('data.remark', 'Quai 3')
        ->assertJsonPath('data.signature.id', $proof->signature_document_id)
        ->assertJsonPath('data.photo.mimeType', 'image/png')
        ->assertJsonMissingPath('data.tourStopId')
        ->assertJsonMissingPath('data.createdBy');

    expect($response->getContent())->not->toContain($proof->signatureDocument->storage_path);

    $this->withHeaders($this->headers)->get("/api/v1/customer/documents/{$proof->signature_document_id}/download")->assertOk();
    $this->withHeaders($this->headers)->get("/api/v1/customer/documents/{$proof->photo_document_id}/download")->assertOk();
});

it('shows a proof without signature nor photo', function (): void {
    $proof = CustomerPortalAfterDelivery::proof($this->order, withFiles: false);

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/proofs-of-delivery/{$proof->id}")->assertOk()
        ->assertJsonPath('data.signature', null)
        ->assertJsonPath('data.photo', null);
});

it('answers 404 for the proof of another customer, and never serves its signature', function (): void {
    $proof = CustomerPortalAfterDelivery::proof(CustomerPortalOrders::order('qoqa'));

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/proofs-of-delivery/{$proof->id}")->assertNotFound();
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/documents/{$proof->signature_document_id}/download")->assertNotFound();
    $this->withHeaders($this->headers)->getJson('/api/v1/customer/proofs-of-delivery/pas-un-ulid')->assertNotFound();
});

it('answers 404 for an incoherent proof, even of its own order', function (): void {
    $proof = CustomerPortalAfterDelivery::proof($this->order, serviceId: CustomerPortalOrders::order('qoqa')->orderServices()->value('id'));

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/proofs-of-delivery/{$proof->id}")->assertNotFound();
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/documents/{$proof->signature_document_id}")->assertNotFound();
});
