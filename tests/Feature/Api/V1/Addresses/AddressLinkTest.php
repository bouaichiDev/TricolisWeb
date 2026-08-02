<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
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
    $this->address = Address::factory()->create();
    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $this->address->id,
        'entity_type' => MorphMap::ORGANIZATION,
        'entity_id' => $this->organization->id,
    ]);
});

describe('address entity links', function (): void {
    it('lists the entities linked to an address', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/addresses/{$this->address->id}/links")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entityType', 'organization');
    });

    it('links an address to another entity of the organization', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/addresses/{$this->address->id}/links", [
                'entityType' => MorphMap::CUSTOMER,
                'entityId' => $this->customer->id,
                'addressType' => 'delivery',
            ])
            ->assertCreated()
            ->assertJsonPath('data.entityId', $this->customer->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'address_linked', 'organization_id' => $this->organization->id]);
    });

    it('refuses a duplicate link', function (): void {
        $payload = ['entityType' => MorphMap::CUSTOMER, 'entityId' => $this->customer->id, 'addressType' => 'delivery'];

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/addresses/{$this->address->id}/links", $payload)->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/addresses/{$this->address->id}/links", $payload)->assertStatus(409);
    });

    it('refuses linking an entity from another organization', function (): void {
        $foreignCustomer = Customer::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/addresses/{$this->address->id}/links", [
                'entityType' => MorphMap::CUSTOMER,
                'entityId' => $foreignCustomer->id,
            ])
            ->assertForbidden();
    });

    it('refuses removing the last link of an address', function (): void {
        $link = EntityAddress::where('address_id', $this->address->id)->firstOrFail();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/addresses/{$this->address->id}/links/{$link->id}")
            ->assertStatus(409);
    });

    it('removes an extra link', function (): void {
        $extra = EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $this->address->id,
            'entity_type' => MorphMap::CUSTOMER,
            'entity_id' => $this->customer->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/addresses/{$this->address->id}/links/{$extra->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('entity_addresses', ['id' => $extra->id]);
    });
});

describe('address contacts', function (): void {
    it('attaches and detaches a contact of the organization', function (): void {
        $contact = Contact::factory()->create();
        EntityContact::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contact->id,
            'entity_type' => MorphMap::ORGANIZATION,
            'entity_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/addresses/{$this->address->id}/contacts", [
                'contactId' => $contact->id,
                'contactRole' => 'delivery',
            ])
            ->assertCreated()
            ->assertJsonPath('data.contactRole', 'delivery');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/addresses/{$this->address->id}/contacts")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/addresses/{$this->address->id}/contacts/{$response->json('data.id')}")
            ->assertNoContent();
    });

    it('refuses a contact from another organization', function (): void {
        $foreignContact = Contact::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/addresses/{$this->address->id}/contacts", ['contactId' => $foreignContact->id])
            ->assertNotFound();
    });
});
