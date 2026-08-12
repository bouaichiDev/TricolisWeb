<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Customers\Models\Customer;
use App\Shared\Database\MorphMap;

/**
 * Lister les adresses et les contacts d'une entité.
 *
 * Le modèle est en étoile : un client porte plusieurs adresses — livraison,
 * facturation — et chaque adresse porte ses propres contacts. Rien de tout cela
 * n'était lisible : `GET /addresses` et `GET /contacts` n'acceptaient pas
 * `entityType` / `entityId`, alors que la création les acceptait déjà. Il
 * fallait lister toute l'organisation puis interroger les liaisons une par une.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->other = Customer::factory()->create(['organization_id' => $this->organization->id]);
});

/** Crée une adresse rattachée à un client, avec son type de liaison. */
function addressFor(Customer $customer, string $organizationId, string $addressType, bool $isDefault = false): Address
{
    $address = Address::factory()->create();

    EntityAddress::create([
        'organization_id' => $organizationId,
        'address_id' => $address->id,
        'entity_type' => MorphMap::CUSTOMER,
        'entity_id' => $customer->id,
        'address_type' => $addressType,
        'is_default' => $isDefault,
    ]);

    return $address;
}

describe('addresses scoped to an entity', function (): void {
    it('lists only the addresses of the requested customer', function (): void {
        addressFor($this->customer, $this->organization->id, 'delivery');
        addressFor($this->customer, $this->organization->id, 'billing');
        addressFor($this->other, $this->organization->id, 'delivery');

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/addresses?entityType='.MorphMap::CUSTOMER.'&entityId='.$this->customer->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        expect(collect($response->json('data'))->pluck('id'))->toHaveCount(2);
    });

    /**
     * Le type est porté par la **liaison**, pas par l'adresse : une même adresse
     * peut être livraison pour un client et facturation pour un autre. C'est ce
     * que `links` expose.
     */
    it('exposes the link carrying the address type', function (): void {
        addressFor($this->customer, $this->organization->id, 'billing', true);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/addresses?entityType='.MorphMap::CUSTOMER.'&entityId='.$this->customer->id)
            ->assertOk();

        expect($response->json('data.0.links.0.addressType'))->toBe('billing')
            ->and($response->json('data.0.links.0.isDefault'))->toBeTrue()
            ->and($response->json('data.0.links.0.entityId'))->toBe($this->customer->id);
    });

    it('omits the links when no entity filter is given', function (): void {
        addressFor($this->customer, $this->organization->id, 'delivery');

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/addresses')
            ->assertOk();

        expect($response->json('data.0'))->not->toHaveKey('links');
    });

    it('refuses an entity type outside the allowed aliases', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/addresses?entityType=order&entityId='.$this->customer->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('entityType');
    });

    it('requires an identifier alongside the entity type', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/addresses?entityType='.MorphMap::CUSTOMER)
            ->assertStatus(422)
            ->assertJsonValidationErrors('entityId');
    });

    /**
     * L'isolation ne dépend pas du filtre : la portée organisationnelle est
     * appliquée avant lui. Un identifiant appartenant à une autre organisation
     * ne ramène rien plutôt que de fuir son contenu.
     */
    it('returns nothing for a customer of another organization', function (): void {
        $foreign = Customer::factory()->create();
        addressFor($foreign, $foreign->organization_id, 'delivery');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/addresses?entityType='.MorphMap::CUSTOMER.'&entityId='.$foreign->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('contacts scoped to an entity', function (): void {
    it('lists only the contacts of the requested customer', function (): void {
        $link = function (Customer $customer, string $role) {
            $contact = Contact::factory()->create();
            EntityContact::create([
                'organization_id' => $this->organization->id,
                'contact_id' => $contact->id,
                'entity_type' => MorphMap::CUSTOMER,
                'entity_id' => $customer->id,
                'contact_role' => $role,
                'is_primary' => false,
            ]);

            return $contact;
        };

        $link($this->customer, 'delivery');
        $link($this->customer, 'billing');
        $link($this->other, 'delivery');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/contacts?entityType='.MorphMap::CUSTOMER.'&entityId='.$this->customer->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('exposes the link carrying the contact role', function (): void {
        $contact = Contact::factory()->create();
        EntityContact::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contact->id,
            'entity_type' => MorphMap::CUSTOMER,
            'entity_id' => $this->customer->id,
            'contact_role' => 'billing',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/contacts?entityType='.MorphMap::CUSTOMER.'&entityId='.$this->customer->id)
            ->assertOk();

        expect($response->json('data.0.links.0.contactRole'))->toBe('billing')
            ->and($response->json('data.0.links.0.isPrimary'))->toBeTrue();
    });

    it('omits the links when no entity filter is given', function (): void {
        $contact = Contact::factory()->create();
        EntityContact::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contact->id,
            'entity_type' => MorphMap::CUSTOMER,
            'entity_id' => $this->customer->id,
            'contact_role' => 'other',
            'is_primary' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/contacts')
            ->assertOk();

        expect($response->json('data.0'))->not->toHaveKey('links');
    });
});
