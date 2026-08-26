<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->payload = ['code' => 'PRV-NEW', 'name' => 'Nouveau transporteur', 'status' => 'active'];
});

describe('providers CRUD', function (): void {
    it('creates a provider in the active organization', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', $this->payload)
            ->assertCreated()
            ->assertJsonPath('data.code', 'PRV-NEW');

        $this->assertDatabaseHas('providers', [
            'id' => $response->json('data.id'),
            'organization_id' => $this->organization->id,
        ]);
    });

    it('reads, updates and deletes a provider', function (): void {
        $provider = Provider::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/providers/{$provider->id}")->assertOk()
            ->assertJsonPath('data.id', $provider->id);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/providers/{$provider->id}", ['name' => 'Renommé', 'status' => 'inactive'])
            ->assertOk()->assertJsonPath('data.name', 'Renommé')->assertJsonPath('data.status', 'inactive');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/providers/{$provider->id}")->assertNoContent();

        $this->assertDatabaseMissing('providers', ['id' => $provider->id]);
    });

    it('accepts an optional address and contact', function (): void {
        // Les deux sont rattachees a l'organisation active : une adresse
        // flottante n'appartient a personne, et l'API n'en cree pas — la
        // creation pose toujours une liaison, a l'organisation a defaut d'autre.
        $address = Address::factory()->create();
        EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $address->id,
            'entity_type' => MorphMap::ORGANIZATION,
            'entity_id' => $this->organization->id,
        ]);

        $contact = Contact::factory()->create();
        EntityContact::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contact->id,
            'entity_type' => MorphMap::ORGANIZATION,
            'entity_id' => $this->organization->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', ['addressId' => $address->id, 'contactId' => $contact->id] + $this->payload)
            ->assertCreated()
            ->assertJsonPath('data.addressId', $address->id)
            ->assertJsonPath('data.contactId', $contact->id);
    });

    it('creates a provider without address nor contact', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', $this->payload)
            ->assertCreated()
            ->assertJsonPath('data.addressId', null)
            ->assertJsonPath('data.contactId', null);
    });

    it('refuses an unknown address or contact', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', ['addressId' => (string) Str::ulid()] + $this->payload)
            ->assertStatus(422)->assertJsonValidationErrors('addressId');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', ['contactId' => (string) Str::ulid()] + $this->payload)
            ->assertStatus(422)->assertJsonValidationErrors('contactId');
    });
});

describe('providers deletion guards', function (): void {
    it('refuses deletion when the provider still has drivers', function (): void {
        $provider = Provider::factory()->forOrganization($this->organization)->create();
        Driver::factory()->forProvider($provider)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/providers/{$provider->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('providers', ['id' => $provider->id]);
    });

    it('refuses deletion when the provider still has vehicles', function (): void {
        $provider = Provider::factory()->forOrganization($this->organization)->create();
        Vehicle::factory()->forProvider($provider)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/providers/{$provider->id}")
            ->assertStatus(409);
    });
});

describe('providers uniqueness and isolation', function (): void {
    it('refuses a duplicated code in the same organization', function (): void {
        Provider::factory()->forOrganization($this->organization)->create(['code' => 'PRV-1']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', ['code' => 'PRV-1'] + $this->payload)
            ->assertStatus(422)->assertJsonValidationErrors('code');
    });

    it('allows the same code in another organization', function (): void {
        $other = Organization::factory()->create();
        Provider::factory()->forOrganization($other)->create(['code' => 'PRV-1']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', ['code' => 'PRV-1'] + $this->payload)
            ->assertCreated();
    });

    it('hides a provider from another organization', function (): void {
        $foreign = Provider::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/providers/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/providers/{$foreign->id}", ['name' => 'Piraté'])->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/providers/{$foreign->id}")->assertNotFound();
    });

    it('lists only the providers of the active organization', function (): void {
        Provider::factory(2)->forOrganization($this->organization)->create();
        Provider::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/providers')->assertOk()
            ->assertJsonCount(3, 'data'); // 2 crees + 1 du seeder de demonstration
    });
});

describe('providers list', function (): void {
    it('searches by code and by name', function (): void {
        Provider::factory()->forOrganization($this->organization)->create(['code' => 'ZZZ-1', 'name' => 'Alpha Logistique']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/providers?search=ZZZ')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/providers?search=Alpha')->assertOk()->assertJsonCount(1, 'data');
    });

    it('filters by status and by address', function (): void {
        $address = Address::factory()->create();
        Provider::factory()->forOrganization($this->organization)
            ->create(['status' => 'blocked', 'address_id' => $address->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/providers?status=blocked')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/providers?addressId={$address->id}")->assertOk()->assertJsonCount(1, 'data');
    });

    it('paginates and rejects a forbidden sort column', function (): void {
        Provider::factory(5)->forOrganization($this->organization)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/providers?perPage=2')->assertOk()->assertJsonCount(2, 'data');
        expect($response->json('meta.perPage'))->toBe(2);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/providers?sort=organization_id')->assertStatus(422);
    });

    it('exposes driver and vehicle counts without loading the collections', function (): void {
        $provider = Provider::factory()->forOrganization($this->organization)->create();
        Driver::factory(2)->forProvider($provider)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/providers?search={$provider->code}")->assertOk();

        expect($response->json('data.0.driverCount'))->toBe(2)
            ->and($response->json('data.0'))->not->toHaveKey('drivers');
    });
});

describe('providers audit', function (): void {
    it('audits creation, update and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', $this->payload)->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'provider.created', 'entity_type' => 'provider', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/providers/$id", ['name' => 'Modifié'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'provider.updated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/providers/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'provider.deleted', 'entity_id' => $id]);
    });

    it('records only the changed fields on update', function (): void {
        $provider = Provider::factory()->forOrganization($this->organization)->create(['name' => 'Avant']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/providers/{$provider->id}", ['name' => 'Après'])->assertOk();

        $log = AuditLog::where('action', 'provider.updated')->firstOrFail();
        expect($log->old_values)->toBe(['name' => 'Avant'])
            ->and($log->new_values)->toBe(['name' => 'Après']);
    });
});

/**
 * L'adresse et le contact d'un fournisseur sont cloisonnés par organisation.
 *
 * `addresses` et `contacts` n'ont pas d'`organization_id` : elles le tiennent
 * de leurs liaisons. Une simple vérification d'existence laissait rattacher
 * l'adresse d'une autre organisation — et la fiche la rendait ensuite lisible.
 */
describe('cloisonnement de l’adresse et du contact', function (): void {
    it('accepts an address linked to the active organization', function (): void {
        $address = Address::factory()->create();
        EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $address->id,
            'entity_type' => MorphMap::ORGANIZATION,
            'entity_id' => $this->organization->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', [
                'code' => 'TRANS-ADDR', 'name' => 'Transports Atlas',
                'status' => 'active', 'addressId' => $address->id,
            ])->assertCreated()->assertJsonPath('data.addressId', $address->id);
    });

    it('refuses an address belonging to another organization', function (): void {
        $other = Organization::factory()->create();
        $address = Address::factory()->create();
        EntityAddress::create([
            'organization_id' => $other->id,
            'address_id' => $address->id,
            'entity_type' => MorphMap::ORGANIZATION,
            'entity_id' => $other->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', [
                'code' => 'TRANS-LEAK', 'name' => 'Transports Atlas',
                'status' => 'active', 'addressId' => $address->id,
            ])->assertStatus(422)->assertJsonValidationErrors('addressId');
    });

    it('refuses a contact belonging to another organization', function (): void {
        $other = Organization::factory()->create();
        $contact = Contact::factory()->create();
        EntityContact::create([
            'organization_id' => $other->id,
            'contact_id' => $contact->id,
            'entity_type' => MorphMap::ORGANIZATION,
            'entity_id' => $other->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', [
                'code' => 'TRANS-LEAK2', 'name' => 'Transports Atlas',
                'status' => 'active', 'contactId' => $contact->id,
            ])->assertStatus(422)->assertJsonValidationErrors('contactId');
    });
});
