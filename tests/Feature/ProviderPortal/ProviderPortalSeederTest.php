<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Models\ProviderUserRole;
use App\Modules\ProviderPortal\Services\ProviderPermissions;
use App\Modules\ProviderPortal\Support\ProviderPortalPermissions;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Modules\Providers\Models\Provider;
use App\Shared\Enums\RoleScope;
use App\Shared\Enums\UserStatus;
use Database\Seeders\ProviderPortalSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Permissions `provider.*` et rôles `PROVIDER_*` : rejouables, et étanches
 * vis-à-vis des rôles internes comme de ceux du portail client.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
});

it('sème les permissions du portail fournisseur et quatre rôles par organisation', function (): void {
    $permissions = Permission::where('code', 'like', 'provider.%')->get();

    expect($permissions->pluck('code')->sort()->values()->all())->toBe(collect(ProviderPortalPermissions::codes())->sort()->values()->all())
        ->and($permissions->pluck('menu_section')->unique()->all())->toBe(['provider_portal'])
        ->and(Role::where('organization_id', $this->organization->id)->where('scope', RoleScope::PROVIDER->value)->pluck('code')->sort()->values()->all())
        ->toBe(['PROVIDER_ADMIN', 'PROVIDER_DISPATCHER', 'PROVIDER_FLEET_MANAGER', 'PROVIDER_VIEWER']);
});

it('se rejoue sans doublon', function (): void {
    $counts = fn () => [Permission::count(), Role::count(), RolePermission::count()];
    $before = $counts();

    $this->seed(ProviderPortalSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ProviderPortalSeeder::class);

    expect($counts())->toBe($before);
});

it('donne à chaque rôle exactement ses permissions, partout, et aucune à un autre rôle', function (): void {
    $this->seed(ProviderPortalSeeder::class);
    // Rejoué après coup, le semis interne ne doit pas ramasser `provider.*`.
    $this->seed(RoleSeeder::class);

    foreach (Organization::all() as $organization) {
        foreach (ProviderPortalRoles::defaults() as $code => $definition) {
            $held = Role::where('organization_id', $organization->id)->where('code', $code)->firstOrFail()
                ->permissions()->pluck('code')->sort()->values()->all();

            expect($held)->toBe(collect($definition['permissions'])->sort()->values()->all());
        }
    }

    $outsiders = Role::where(fn ($query) => $query->whereNull('scope')->orWhere('scope', '!=', RoleScope::PROVIDER->value))
        ->whereHas('permissions', fn ($query) => $query->where('code', 'like', 'provider.%'))
        ->count();

    expect($outsiders)->toBe(0);
});

it('ne compte que les rôles fournisseur actifs de l’organisation du fournisseur', function (): void {
    $provider = Provider::where('code', 'demo-provider')->firstOrFail();
    $user = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.test', 'password' => 'x-password-1', 'status' => UserStatus::ACTIVE->value]);
    $membership = ProviderUser::create(['provider_id' => $provider->id, 'user_id' => $user->id, 'status' => 'active']);
    $other = Organization::factory()->create();
    $this->seed(ProviderPortalSeeder::class);
    $foreign = Role::where('organization_id', $other->id)->where('code', ProviderPortalRoles::ADMIN)->firstOrFail();
    $customer = Role::where('organization_id', $provider->organization_id)->where('code', 'CUSTOMER_ADMIN')->firstOrFail();
    ProviderUserRole::create(['provider_user_id' => $membership->id, 'role_id' => $foreign->id]);
    ProviderUserRole::create(['provider_user_id' => $membership->id, 'role_id' => $customer->id]);

    expect(ProviderPermissions::codesFor($membership->fresh()))->toBe([]);

    $own = Role::where('organization_id', $provider->organization_id)->where('code', ProviderPortalRoles::VIEWER)->firstOrFail();
    ProviderUserRole::create(['provider_user_id' => $membership->id, 'role_id' => $own->id]);

    expect(ProviderPermissions::codesFor($membership->fresh()))->toBe(collect(ProviderPortalRoles::defaults()[ProviderPortalRoles::VIEWER]['permissions'])->sort()->values()->all());

    $own->update(['status' => 'inactive']);

    expect(ProviderPermissions::codesFor($membership->fresh()))->toBe([]);
});

it('tient les rôles fournisseur hors de la liste des rôles internes, sauf demande explicite', function (): void {
    $internal = $this->actingAs(authUser())->withHeaders(['X-Organization-Id' => $this->organization->id]);

    $codes = collect($internal->getJson('/api/v1/roles?perPage=100')->assertOk()->json('data'))->pluck('code');
    $providerCodes = collect($internal->getJson('/api/v1/roles?scope=provider&perPage=100')->assertOk()->json('data'))->pluck('code');

    expect($codes->filter(fn (string $code): bool => str_starts_with($code, 'PROVIDER_'))->all())->toBe([])
        ->and($providerCodes->sort()->values()->all())->toBe(['PROVIDER_ADMIN', 'PROVIDER_DISPATCHER', 'PROVIDER_FLEET_MANAGER', 'PROVIDER_VIEWER']);
});

it('accorde au rejeu les permissions de la phase 2 aux rôles déjà semés, selon leur métier', function (): void {
    $codes = ['provider.drivers.view', 'provider.drivers.create', 'provider.drivers.update', 'provider.vehicles.view', 'provider.vehicles.create', 'provider.vehicles.update'];
    // Base semée avant la phase 2 : ces permissions n'existaient pas encore.
    Permission::whereIn('code', $codes)->delete();

    $this->seed(ProviderPortalSeeder::class);

    $held = fn (string $role): array => Role::where('organization_id', $this->organization->id)->where('code', $role)->firstOrFail()
        ->permissions()->whereIn('code', $codes)->pluck('code')->sort()->values()->all();

    expect($held('PROVIDER_ADMIN'))->toBe(collect($codes)->sort()->values()->all())
        ->and($held('PROVIDER_FLEET_MANAGER'))->toBe(collect($codes)->sort()->values()->all())
        ->and($held('PROVIDER_DISPATCHER'))->toBe(['provider.drivers.view', 'provider.vehicles.view'])
        ->and($held('PROVIDER_VIEWER'))->toBe(['provider.drivers.view', 'provider.vehicles.view']);
});

it('accorde au rejeu les permissions de la phase 3 selon le métier de chaque rôle', function (): void {
    $codes = ['provider.tours.view', 'provider.tours.assign-resources', 'provider.calendar.view'];
    Permission::whereIn('code', $codes)->delete();

    $this->seed(ProviderPortalSeeder::class);

    $held = fn (string $role): array => Role::where('organization_id', $this->organization->id)->where('code', $role)->firstOrFail()
        ->permissions()->whereIn('code', $codes)->pluck('code')->sort()->values()->all();

    expect($held('PROVIDER_ADMIN'))->toBe(['provider.calendar.view', 'provider.tours.assign-resources', 'provider.tours.view'])
        ->and($held('PROVIDER_DISPATCHER'))->toBe(['provider.calendar.view', 'provider.tours.assign-resources', 'provider.tours.view'])
        ->and($held('PROVIDER_FLEET_MANAGER'))->toBe(['provider.calendar.view', 'provider.tours.view'])
        ->and($held('PROVIDER_VIEWER'))->toBe(['provider.calendar.view', 'provider.tours.view']);
});

it('accorde au rejeu les permissions d’exécution à l’administrateur et à l’exploitant seulement', function (): void {
    $codes = ['provider.tours.execute', 'provider.tour-stops.update-status'];
    Permission::whereIn('code', $codes)->delete();

    $this->seed(ProviderPortalSeeder::class);

    $holders = Role::where('organization_id', $this->organization->id)
        ->whereHas('permissions', fn ($query) => $query->where('code', 'provider.tours.execute'))
        ->pluck('code')->sort()->values()->all();

    expect($holders)->toBe(['PROVIDER_ADMIN', 'PROVIDER_DISPATCHER'])
        ->and(Permission::where('code', 'provider.tour-stops.update-status')->exists())->toBeTrue();
});

it('accorde au rejeu les permissions de suivi, documents et preuves selon le métier', function (): void {
    $codes = ['provider.tracking.view', 'provider.documents.view', 'provider.pod.view', 'provider.pod.create'];
    Permission::whereIn('code', $codes)->delete();

    $this->seed(ProviderPortalSeeder::class);

    $held = fn (string $role): array => Role::where('organization_id', $this->organization->id)->where('code', $role)->firstOrFail()
        ->permissions()->whereIn('code', $codes)->pluck('code')->sort()->values()->all();
    $all = collect($codes)->sort()->values()->all();
    $read = ['provider.documents.view', 'provider.pod.view', 'provider.tracking.view'];

    expect($held('PROVIDER_ADMIN'))->toBe($all)->and($held('PROVIDER_DISPATCHER'))->toBe($all)
        ->and($held('PROVIDER_FLEET_MANAGER'))->toBe($read)->and($held('PROVIDER_VIEWER'))->toBe($read);
});
