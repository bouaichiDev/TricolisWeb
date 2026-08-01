<?php

use App\Modules\Drivers\Models\Driver;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Providers\Models\Provider;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->provider = Provider::factory()->forOrganization($this->organization)->create();
    $this->payload = fn (array $o = []): array => array_merge([
        'providerId' => $this->provider->id,
        'code' => 'DRV-NEW',
        'firstName' => 'Yassine',
        'lastName' => 'Bennani',
        'phone' => '+212661223344',
        'email' => 'yassine@transport.dev',
        'status' => 'active',
    ], $o);
});

describe('drivers CRUD', function (): void {
    it('creates a driver under a provider of the active organization', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.code', 'DRV-NEW')
            ->assertJsonPath('data.fullName', 'Yassine Bennani')
            ->assertJsonPath('data.providerId', $this->provider->id);
    });

    it('reads, updates and deletes a driver', function (): void {
        $driver = Driver::factory()->forProvider($this->provider)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/drivers/{$driver->id}")->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/drivers/{$driver->id}", ['firstName' => 'Modifié'])
            ->assertOk()->assertJsonPath('data.firstName', 'Modifié');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/drivers/{$driver->id}")->assertNoContent();
    });

    it('links a user of the active organization', function (): void {
        $membership = OrganizationUser::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['userId' => $membership->user_id]))
            ->assertCreated()
            ->assertJsonPath('data.userId', $membership->user_id);
    });

    it('exposes only identity fields of the linked user', function (): void {
        $membership = OrganizationUser::factory()->forOrganization($this->organization)->create();
        $driver = Driver::factory()->forProvider($this->provider)->create(['user_id' => $membership->user_id]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/drivers/{$driver->id}")->assertOk();

        expect(array_keys($response->json('data.user')))->toBe(['id', 'fullName', 'email']);
    });
});

describe('drivers foreign keys', function (): void {
    it('refuses a provider from another organization', function (): void {
        $foreignProvider = Provider::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['providerId' => $foreignProvider->id]))
            ->assertStatus(422)->assertJsonValidationErrors('providerId');
    });

    it('refuses a user outside the active organization', function (): void {
        $foreignUser = User::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['userId' => $foreignUser->id]))
            ->assertStatus(422)->assertJsonValidationErrors('userId');
    });

    it('refuses moving a driver to a provider outside the organization', function (): void {
        $driver = Driver::factory()->forProvider($this->provider)->create();
        $foreignProvider = Provider::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/drivers/{$driver->id}", ['providerId' => $foreignProvider->id])
            ->assertStatus(422)->assertJsonValidationErrors('providerId');
    });

    it('rejects an invalid email', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['email' => 'pas-un-email']))
            ->assertStatus(422)->assertJsonValidationErrors('email');
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

    it('hides a driver whose provider belongs to another organization', function (): void {
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

    it('searches by name and by email', function (): void {
        Driver::factory()->forProvider($this->provider)->create(['last_name' => 'Zerktouni', 'email' => 'zerk@transport.dev']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/drivers?search=Zerktouni')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/drivers?search=zerk@')->assertOk()->assertJsonCount(1, 'data');
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
