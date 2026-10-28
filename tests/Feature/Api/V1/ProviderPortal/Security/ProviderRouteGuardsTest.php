<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Support\ProviderRouteSecurity;
use App\Shared\Enums\RoleScope;
use App\Shared\Enums\UserStatus;
use Database\Seeders\CustomerPortalDemoSeeder;
use Database\Seeders\DemoProviderPortalSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\ProviderPortal;

/**
 * Audit automatisé des routes du portail fournisseur (Phase 7) : piloté par
 * le routeur réel, il couvre d'office toute route ajoutée demain. Chaque route
 * authentifiée refuse l'anonyme, les jetons interne et client, et — si elle
 * exige une permission — un membre qui n'en a aucune.
 */
const PROVIDER_GUARD_NOWHERE = '01JZZZZZZZZZZZZZZZZZZZZZZZ';

function providerGuardRoutes(): array
{
    return array_values(array_filter(
        app(ProviderRouteSecurity::class)->rows(),
        static fn (array $row): bool => $row['context'] === 'provider.portal',
    ));
}

function providerGuardFill(string $uri, string $id = PROVIDER_GUARD_NOWHERE): string
{
    return '/'.preg_replace('/\{\w+\??\}/', $id, $uri);
}

function providerGuardMethod(array $route): string
{
    return explode('|', $route['method'])[0];
}

beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->routes = providerGuardRoutes();
});

it('inventorie toutes les routes, chacune protégée explicitement', function (): void {
    $gaps = array_filter(app(ProviderRouteSecurity::class)->rows(), static fn (array $row): bool => $row['gap'] !== null);

    expect(count($this->routes))->toBeGreaterThanOrEqual(38)
        ->and(array_map(static fn (array $row): string => "{$row['method']} {$row['uri']} : {$row['gap']}", $gaps))->toBe([]);
});

it('refuse toute route authentifiée sans jeton (401)', function (): void {
    $answers = [];

    foreach ($this->routes as $route) {
        $status = $this->json(providerGuardMethod($route), providerGuardFill($route['uri']))->status();
        if ($status !== 401) {
            $answers[] = "{$route['method']} {$route['uri']} → {$status}";
        }
    }

    expect($answers)->toBe([]);
});

it('refuse toute route aux jetons interne et client (403 provider_access_revoked)', function (): void {
    $this->seed(CustomerPortalDemoSeeder::class);
    $answers = [];

    foreach (['interne' => ProviderPortal::bearer(ProviderPortal::internalToken(authUser())), 'client' => CustomerPortal::headers('admin@ikea.test')] as $who => $headers) {
        foreach ($this->routes as $route) {
            $response = $this->withHeaders($headers)->json(providerGuardMethod($route), providerGuardFill($route['uri']));
            if ($response->status() !== 403 || $response->json('code') !== 'provider_access_revoked') {
                $answers[] = "{$who} : {$route['method']} {$route['uri']} → {$response->status()}";
            }
        }
    }

    expect($answers)->toBe([]);
});

it('refuse chaque route à permission à un membre qui n’a que l’accès au portail (403)', function (): void {
    // Un rôle qui ouvre le portail et rien d'autre : le contexte passe, seule
    // la permission de la route peut refuser.
    $alpha = ProviderPortal::provider('transports-alpha');
    $role = Role::create(['organization_id' => $alpha->organization_id, 'code' => 'PROVIDER_TEST_ACCESS_ONLY', 'name' => 'Accès seul', 'scope' => RoleScope::PROVIDER->value, 'is_system' => false, 'status' => 'active']);
    RolePermission::create(['role_id' => $role->id, 'permission_id' => Permission::where('code', 'provider.portal.access')->value('id')]);
    $user = User::create(['first_name' => 'Accès', 'last_name' => 'Seul', 'email' => 'acces-seul@transports-alpha.test', 'password' => 'x-password-1', 'status' => UserStatus::ACTIVE->value]);
    ProviderUser::create(['provider_id' => $alpha->id, 'user_id' => $user->id, 'status' => 'active'])->roles()->attach($role->id, ['id' => (string) str()->ulid()]);
    $headers = ProviderPortal::headers('acces-seul@transports-alpha.test');
    $guarded = array_filter($this->routes, static fn (array $route): bool => $route['permission'] !== []);
    $answers = [];

    $this->withHeaders($headers)->getJson('/api/v1/provider/auth/me')->assertOk();

    foreach ($guarded as $route) {
        $response = $this->withHeaders($headers)->json(providerGuardMethod($route), providerGuardFill($route['uri']));
        if ($response->status() !== 403 || $response->json('code') === 'provider_access_revoked') {
            $answers[] = "{$route['method']} {$route['uri']} → {$response->status()}";
        }
    }

    expect(count($guarded))->toBeGreaterThan(30)->and($answers)->toBe([]);
});

it('ne laisse aucune route de gestion des utilisateurs ni des rôles', function (): void {
    $forbidden = array_filter($this->routes, static fn (array $route): bool => (bool) preg_match('#/(users|roles|permissions|invitations)(/|$)#', $route['uri']));

    expect(array_column($forbidden, 'uri'))->toBe([]);
});
