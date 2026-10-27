<?php

use App\Modules\CustomerPortal\Support\CustomerPortalPermissions;
use App\Modules\CustomerPortal\Support\CustomerPortalRoles;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Enums\RoleScope;
use Database\Seeders\CustomerPortalDemoSeeder;
use Database\Seeders\CustomerPortalSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Seeders du portail : rejouables, et étanches vis-à-vis de l'interne.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->organization = authOrganization();
});

it('seeds every customer permission and the four default roles', function (): void {
    expect(Permission::customerPortal()->count())->toBe(count(CustomerPortalPermissions::codes()))
        ->and(Permission::where('code', 'customer.stock.quantity.update')->exists())->toBeFalse()
        ->and(Role::where('organization_id', $this->organization->id)->where('scope', RoleScope::CUSTOMER->value)->count())->toBe(4);
});

it('can be replayed without duplicates', function (): void {
    $counts = fn () => [Permission::count(), Role::count(), RolePermission::count()];
    $before = $counts();

    $this->seed(CustomerPortalSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(CustomerPortalSeeder::class);

    expect($counts())->toBe($before);
});

it('gives the article update to the admin and the stock manager only, even on a replayed base', function (): void {
    $code = 'customer.articles.update';
    // Base déjà en place avant la permission : elle n'existait pas encore.
    Permission::where('code', $code)->delete();

    $this->seed(CustomerPortalSeeder::class);

    $holders = Role::where('organization_id', $this->organization->id)
        ->whereHas('permissions', fn ($query) => $query->where('code', $code))
        ->pluck('code')->sort()->values()->all();

    expect($holders)->toBe(['CUSTOMER_ADMIN', 'CUSTOMER_STOCK_MANAGER']);
});

it('gives every default role exactly its declared permissions, in every organization, after replays', function (): void {
    $this->seed(CustomerPortalSeeder::class);
    $this->seed(CustomerPortalSeeder::class);

    foreach (Organization::all() as $organization) {
        foreach (CustomerPortalRoles::defaults() as $code => $definition) {
            $held = Role::where('organization_id', $organization->id)->where('code', $code)->firstOrFail()
                ->permissions()->pluck('code')->sort()->values()->all();

            expect($held)->toBe(collect($definition['permissions'])->sort()->values()->all(), "{$organization->code} / {$code}");
        }
    }
});

it('keeps the carrier adjustments of a customer role on replay', function (): void {
    $viewer = Role::where('organization_id', $this->organization->id)->where('code', 'CUSTOMER_VIEWER')->firstOrFail();
    RolePermission::where('role_id', $viewer->id)
        ->whereIn('permission_id', Permission::where('code', 'customer.stock.view')->pluck('id'))
        ->delete();

    $this->seed(CustomerPortalSeeder::class);

    expect($viewer->permissions()->where('code', 'customer.stock.view')->exists())->toBeFalse();
});

it('never gives customer permissions to internal roles', function (): void {
    $internal = Role::where('scope', '!=', RoleScope::CUSTOMER->value)->pluck('id');

    expect(RolePermission::whereIn('role_id', $internal)
        ->whereIn('permission_id', Permission::customerPortal()->pluck('id'))
        ->exists())->toBeFalse();
});

it('hides customer permissions and roles from the internal administration', function (): void {
    $headers = ['X-Organization-Id' => $this->organization->id];

    $permissions = collect($this->actingAs(authUser(), 'sanctum')->withHeaders($headers)
        ->getJson('/api/v1/permissions?perPage=100&search=customer.')->assertOk()->json('data'))->pluck('code');
    $roles = collect($this->withHeaders($headers)->getJson('/api/v1/roles?perPage=100')->assertOk()->json('data'))->pluck('code');

    expect($permissions->filter(fn (string $code) => str_starts_with($code, 'customer.')))->toBeEmpty()
        ->and($roles)->not->toContain('CUSTOMER_ADMIN');
});
