<?php

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Customers\Models\Customer;
use App\Shared\Database\MorphMap;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::where('organization_id', $this->organization->id)->firstOrFail();
    $this->contact = Contact::factory()->create();
    EntityContact::create([
        'organization_id' => $this->organization->id,
        'contact_id' => $this->contact->id,
        'entity_type' => MorphMap::ORGANIZATION,
        'entity_id' => $this->organization->id,
    ]);
});

describe('contact entity links', function (): void {
    it('lists the entities linked to a contact', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/contacts/{$this->contact->id}/links")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entityType', 'organization');
    });

    it('links a contact to a customer with a business role', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/contacts/{$this->contact->id}/links", [
                'entityType' => MorphMap::CUSTOMER,
                'entityId' => $this->customer->id,
                'contactRole' => 'billing',
                'notifyByEmail' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.contactRole', 'billing')
            ->assertJsonPath('data.notifyByEmail', true);
    });

    it('rejects an unknown contact role', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/contacts/{$this->contact->id}/links", [
                'entityType' => MorphMap::CUSTOMER,
                'entityId' => $this->customer->id,
                'contactRole' => 'unknown-role',
            ])
            ->assertStatus(422);
    });

    it('refuses removing the last link of a contact', function (): void {
        $link = EntityContact::where('contact_id', $this->contact->id)->firstOrFail();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/contacts/{$this->contact->id}/links/{$link->id}")
            ->assertStatus(409);
    });

    it('removes an extra link', function (): void {
        $extra = EntityContact::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->contact->id,
            'entity_type' => MorphMap::CUSTOMER,
            'entity_id' => $this->customer->id,
            'contact_role' => 'operations',
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/contacts/{$this->contact->id}/links/{$extra->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('entity_contacts', ['id' => $extra->id]);
    });
});
