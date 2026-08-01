<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Providers\Models\Provider;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->provider = Provider::factory()->forOrganization($this->organization)->create();
    $this->payload = fn (array $o = []): array => array_merge([
        'providerId' => $this->provider->id,
        'code' => 'DRV-NEW',
        'name' => 'Yassine Bennani',
        'status' => 'active',
    ], $o);
});

describe('drivers CRUD', function (): void {
    it('creates a driver under a provider of the active organization', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.code', 'DRV-NEW')
            ->assertJsonPath('data.name', 'Yassine Bennani')
            ->assertJsonPath('data.providerId', $this->provider->id);
    });

    it('inherits the organization of its provider', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)())->assertCreated()
            ->assertJsonPath('data.organizationId', $this->organization->id);

        $this->assertDatabaseHas('drivers', [
            'id' => $response->json('data.id'),
            'organization_id' => $this->organization->id,
        ]);
    });

    it('reads, updates and deletes a driver', function (): void {
        $driver = Driver::factory()->forProvider($this->provider)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/drivers/{$driver->id}")->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/drivers/{$driver->id}", ['name' => 'Modifié'])
            ->assertOk()->assertJsonPath('data.name', 'Modifié');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/drivers/{$driver->id}")->assertNoContent();
    });

    it('accepts an optional address and contact', function (): void {
        $address = Address::factory()->create();
        $contact = Contact::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)([
                'addressId' => $address->id,
                'contactId' => $contact->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.addressId', $address->id)
            ->assertJsonPath('data.contactId', $contact->id);
    });

    it('creates a driver without address nor contact', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.addressId', null)
            ->assertJsonPath('data.contactId', null);
    });
});

describe('drivers foreign keys', function (): void {
    it('refuses a provider from another organization', function (): void {
        $foreignProvider = Provider::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['providerId' => $foreignProvider->id]))
            ->assertStatus(422)->assertJsonValidationErrors('providerId');
    });

    it('refuses moving a driver to a provider outside the organization', function (): void {
        $driver = Driver::factory()->forProvider($this->provider)->create();
        $foreignProvider = Provider::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/drivers/{$driver->id}", ['providerId' => $foreignProvider->id])
            ->assertStatus(422)->assertJsonValidationErrors('providerId');
    });

    it('refuses an unknown address or contact', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['addressId' => (string) Str::ulid()]))
            ->assertStatus(422)->assertJsonValidationErrors('addressId');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['contactId' => (string) Str::ulid()]))
            ->assertStatus(422)->assertJsonValidationErrors('contactId');
    });
});

describe('drivers uniqueness and isolation', function (): void {
    it('refuses a duplicated code for the same provider', function (): void {
        Driver::factory()->forProvider($this->provider)->create(['code' => 'DRV-1']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['code' => 'DRV-1']))
            ->assertStatus(422)->assertJsonValidationErrors('code');
    });

    it('allows the same code for another provider', function (): void {
        Driver::factory()->forProvider($this->provider)->create(['code' => 'DRV-1']);
        $second = Provider::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['code' => 'DRV-1', 'providerId' => $second->id]))
            ->assertCreated();
    });

    it('hides a driver of another organization', function (): void {
        $foreignDriver = Driver::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/drivers/{$foreignDriver->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/drivers/{$foreignDriver->id}")->assertNotFound();
    });

    it('lists and filters only drivers of the active organization', function (): void {
        Driver::factory(2)->forProvider($this->provider)->create();
        Driver::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/drivers?providerId={$this->provider->id}")
            ->assertOk()->assertJsonCount(2, 'data');
    });

    it('searches by code and by name', function (): void {
        Driver::factory()->forProvider($this->provider)->create(['code' => 'ZZZ-9', 'name' => 'Nadia Zerktouni']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/drivers?search=Zerktouni')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/drivers?search=ZZZ-9')->assertOk()->assertJsonCount(1, 'data');
    });

    it('rejects a forbidden sort column', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/drivers?sort=provider_id')->assertStatus(422);
    });
});

describe('drivers audit', function (): void {
    it('audits creation, update and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'driver.created', 'entity_type' => 'driver', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/drivers/$id", ['status' => 'inactive'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'driver.updated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/drivers/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'driver.deleted', 'entity_id' => $id]);
    });
});
