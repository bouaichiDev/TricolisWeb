<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use Database\Seeders\DemoProviderPortalSeeder;
use Tests\Support\ProviderPortal;

/**
 * Navigation, profil et mot de passe du membre connecté au portail fournisseur.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
});

const CURRENT_PASSWORD = 'password';
const NEXT_PASSWORD = 'Motdepasse-Solide-2026!';

it('rend la navigation que les permissions ouvrent, jamais de gestion de comptes', function (): void {
    $keys = collect($this->withHeaders($this->admin)->getJson('/api/v1/provider/navigation')->assertOk()->json('data'))->pluck('key');

    expect($keys->all())->toBe(['dashboard', 'profile'])
        ->and($keys)->not->toContain('users')->not->toContain('roles');
});

it('retire le profil de la navigation et le ferme sans `provider.profile.view`', function (): void {
    Role::where('organization_id', authOrganization()->id)->where('code', ProviderPortalRoles::VIEWER)->firstOrFail()
        ->permissions()->detach(Permission::where('code', 'provider.profile.view')->value('id'));
    $viewer = ProviderPortal::headers('viewer@transports-alpha.test');

    expect(collect($this->withHeaders($viewer)->getJson('/api/v1/provider/navigation')->json('data'))->pluck('key')->all())->toBe(['dashboard']);
    $this->withHeaders($viewer)->getJson('/api/v1/provider/profile')->assertForbidden();
});

it('montre le profil du fournisseur en lecture seule', function (): void {
    $this->withHeaders($this->admin)->getJson('/api/v1/provider/profile')->assertOk()
        ->assertJsonPath('data.provider.code', 'transports-alpha')
        ->assertJsonPath('data.provider.name', 'Transports Alpha')
        ->assertJsonPath('data.provider.address', null)
        ->assertJsonPath('data.user.email', 'admin@transports-alpha.test');
});

it('modifie le nom du compte, jamais le fournisseur', function (): void {
    $this->withHeaders($this->admin)->putJson('/api/v1/provider/profile', ['firstName' => 'Alicia'])->assertOk()
        ->assertJsonPath('data.user.firstName', 'Alicia');

    $this->withHeaders($this->admin)->putJson('/api/v1/provider/profile', ['providerId' => ProviderPortal::provider('transports-beta')->id])
        ->assertUnprocessable()->assertJsonValidationErrors('providerId');
});

it('change le mot de passe : ancien vérifié, autres sessions fermées, session courante gardée', function (): void {
    $current = ProviderPortal::token('admin@transports-alpha.test');
    $other = ProviderPortal::token('admin@transports-alpha.test');
    $payload = ['currentPassword' => CURRENT_PASSWORD, 'password' => NEXT_PASSWORD, 'password_confirmation' => NEXT_PASSWORD];

    $this->withHeaders(ProviderPortal::bearer($current))->putJson('/api/v1/provider/profile/password', [...$payload, 'currentPassword' => 'faux'])
        ->assertUnprocessable()->assertJsonValidationErrors('currentPassword');

    $this->withHeaders(ProviderPortal::bearer($current))->putJson('/api/v1/provider/profile/password', $payload)->assertOk();

    $this->withHeaders(ProviderPortal::bearer($other))->getJson('/api/v1/provider/auth/me')->assertUnauthorized();
    $this->withHeaders(ProviderPortal::bearer($current))->getJson('/api/v1/provider/auth/me')->assertOk();
});
