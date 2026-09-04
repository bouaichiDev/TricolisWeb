<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Providers\Models\Provider;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->provider = Provider::factory()->forOrganization($this->organization)->create();
    // L'identite sert au chauffeur et a son compte : `name` est compose du
    // prenom et du nom, et l'adresse ouvrira l'application mobile.
    $this->payload = fn (array $o = []): array => array_merge([
        'providerId' => $this->provider->id,
        'code' => 'DRV-NEW',
        'firstName' => 'Yassine',
        'lastName' => 'Bennani',
        'email' => 'yassine.bennani@example.test',
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
        // Rattachees a l'organisation active : une adresse flottante
        // n'appartient a personne, et l'API n'en cree pas — toute creation pose
        // une liaison, a l'organisation a defaut d'autre.
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
    it('refuses a duplicated code in the organization', function (): void {
        Driver::factory()->forProvider($this->provider)->create(['code' => 'DRV-1']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['code' => 'DRV-1']))
            ->assertStatus(422)->assertJsonValidationErrors('code');
    });

    /**
     * Le code identifie un chauffeur dans toute l'organisation.
     *
     * Il etait unique par fournisseur ; depuis qu'un chauffeur peut ne pas en
     * avoir, MySQL aurait laisse passer autant de doublons que de chauffeurs
     * sans fournisseur — deux `NULL` sont distincts pour lui.
     */
    it('refuses the same code under another provider of the organization', function (): void {
        Driver::factory()->forProvider($this->provider)->create(['code' => 'DRV-1']);
        $second = Provider::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['code' => 'DRV-1', 'providerId' => $second->id]))
            ->assertStatus(422)->assertJsonValidationErrors('code');
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

/**
 * Un transporteur emploie ses propres chauffeurs, et chaque chauffeur créé
 * depuis l'application a son compte.
 */
describe('chauffeur du transporteur et compte', function (): void {
    it('creates a driver without any provider', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['providerId' => null]))
            ->assertCreated()
            ->assertJsonPath('data.providerId', null)
            ->assertJsonPath('data.organizationId', $this->organization->id);

        $this->assertDatabaseHas('drivers', [
            'id' => $response->json('data.id'),
            'provider_id' => null,
            'organization_id' => $this->organization->id,
        ]);
    });

    it('creates the account, its membership and the driver role', function (): void {
        $id = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)())
            ->assertCreated()->json('data.id');

        $driver = Driver::findOrFail($id);
        expect($driver->user_id)->not->toBeNull();

        $user = User::findOrFail($driver->user_id);
        expect($user->email)->toBe('yassine.bennani@example.test');
        // Le compte nait invite : son mot de passe est tire au hasard, et il
        // s'active par la reinitialisation comme tout membre invite.
        expect($user->status->value ?? $user->status)->toBe('invited');

        $membership = OrganizationUser::where('organization_id', $this->organization->id)
            ->where('user_id', $user->id)->firstOrFail();

        $role = Role::where('organization_id', $this->organization->id)
            ->where('code', 'driver')->firstOrFail();

        $this->assertDatabaseHas('user_roles', [
            'organization_user_id' => $membership->id,
            'role_id' => $role->id,
        ]);

        // Le role du chauffeur n'ouvre rien dans le back-office.
        expect($role->permissions()->count())->toBe(0);
    });

    it('refuses an email already used by another account', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)())->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)(['code' => 'DRV-2']))
            ->assertStatus(422)->assertJsonValidationErrors('email');
    });

    it('exposes the linked account on the detail', function (): void {
        $id = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', ($this->payload)())->assertCreated()->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/drivers/$id")->assertOk()
            ->assertJsonPath('data.user.email', 'yassine.bennani@example.test')
            ->assertJsonPath('data.user.firstName', 'Yassine');
    });
});

/**
 * Le lien se lit dans les deux sens : depuis le chauffeur on atteint son
 * compte, et depuis le compte on retrouve le chauffeur.
 */
it('exposes the driver on the membership of its account', function (): void {
    $driverId = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/drivers', ($this->payload)())->assertCreated()->json('data.id');

    $driver = Driver::findOrFail($driverId);
    $membership = OrganizationUser::where('organization_id', $this->organization->id)
        ->where('user_id', $driver->user_id)->firstOrFail();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/organization-users/{$membership->id}")->assertOk()
        ->assertJsonPath('data.driver.id', $driverId)
        ->assertJsonPath('data.driver.code', 'DRV-NEW');
});

it('leaves the driver null on a membership that drives nothing', function (): void {
    $membership = OrganizationUser::where('organization_id', $this->organization->id)
        ->where('user_id', $this->user->id)->firstOrFail();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/organization-users/{$membership->id}")->assertOk()
        ->assertJsonPath('data.driver', null);
});
