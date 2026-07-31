<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLink;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\MorphMap;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::where('organization_id', $this->organization->id)->firstOrFail();
    $this->document = Document::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);
});

describe('document links', function (): void {
    it('links a document to a customer of the active organization', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/documents/{$this->document->id}/links", [
                'entityType' => MorphMap::CUSTOMER,
                'entityId' => $this->customer->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.entityType', 'customer')
            ->assertJsonPath('data.entityId', $this->customer->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document_linked',
            'entity_type' => 'document_link',
        ]);
    });

    it('lists the entities linked to a document', function (): void {
        $this->document->links()->create(['entity_type' => MorphMap::ORGANIZATION, 'entity_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/documents/{$this->document->id}/links")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('refuses a duplicated link', function (): void {
        $payload = ['entityType' => MorphMap::CUSTOMER, 'entityId' => $this->customer->id];

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/documents/{$this->document->id}/links", $payload)->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/documents/{$this->document->id}/links", $payload)->assertStatus(409);
    });

    it('refuses an entity from another organization', function (): void {
        $foreignCustomer = Customer::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/documents/{$this->document->id}/links", [
                'entityType' => MorphMap::CUSTOMER,
                'entityId' => $foreignCustomer->id,
            ])
            ->assertForbidden();
    });

    it('refuses an unknown entity type', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/documents/{$this->document->id}/links", [
                'entityType' => 'spaceship',
                'entityId' => $this->customer->id,
            ])
            ->assertNotFound();
    });

    it('detaches a link', function (): void {
        $link = $this->document->links()->create(['entity_type' => MorphMap::CUSTOMER, 'entity_id' => $this->customer->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/documents/{$this->document->id}/links/{$link->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('document_links', ['id' => $link->id]);
    });

    it('refuses a link belonging to another document', function (): void {
        $other = Document::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);
        $link = $other->links()->create(['entity_type' => MorphMap::CUSTOMER, 'entity_id' => $this->customer->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/documents/{$this->document->id}/links/{$link->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('document_links', ['id' => $link->id]);
    });

    it('never exposes the links of a document from another organization', function (): void {
        $foreign = Organization::factory()->create();
        $foreignDocument = Document::factory()->forOrganization($foreign)->create(['created_by' => $this->user->id]);
        DocumentLink::create(['document_id' => $foreignDocument->id, 'entity_type' => MorphMap::ORGANIZATION, 'entity_id' => $foreign->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/documents/{$foreignDocument->id}/links")
            ->assertForbidden();
    });
});
