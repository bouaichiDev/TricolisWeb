<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Modules\ProviderPortal\Support\ProviderPortalToken;
use App\Shared\Enums\OrganizationStatus;
use App\Shared\Enums\UserStatus;
use Database\Seeders\CustomerPortalDemoSeeder;
use Database\Seeders\DemoProviderPortalSeeder;
use Illuminate\Support\Facades\Route;
use Tests\Support\CustomerPortal;
use Tests\Support\ProviderPortal;

/**
 * La chaîne du portail fournisseur : jeton portail → compte actif →
 * rattachement actif → fournisseur actif → organisation ouverte → accès.
 * Chaque maillon est revérifié à chaque requête, et aucun jeton n'ouvre une
 * autre porte que la sienne.
 */
const PROBE = '/api/v1/provider/__probe';

beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    // Route sonde, propre au test : aucune route de production en double.
    Route::middleware(['api', 'auth:sanctum', 'provider.portal'])
        ->get(PROBE, static fn () => response()->json(['providerId' => app(CurrentProviderContext::class)->providerId()]));
    $this->alpha = ProviderPortal::headers('admin@transports-alpha.test');
});

function revoked($response): void
{
    $response->assertForbidden()->assertJsonPath('code', 'provider_access_revoked');
}

it('ouvre le portail au membre actif et résout son fournisseur depuis le jeton', function (): void {
    $this->withHeaders($this->alpha)->getJson(PROBE)
        ->assertOk()
        ->assertJsonPath('providerId', ProviderPortal::provider('transports-alpha')->id);
});

it('ferme l’interne et le portail client au jeton fournisseur, même pour un collaborateur interne', function (): void {
    $user = authUser();
    ProviderUser::create(['provider_id' => ProviderPortal::provider('transports-alpha')->id, 'user_id' => $user->id, 'status' => 'active'])
        ->roles()->attach(Role::where('code', ProviderPortalRoles::ADMIN)->where('organization_id', authOrganization()->id)->value('id'), ['id' => (string) str()->ulid()]);
    $token = $user->createToken(ProviderPortalToken::NAME, ProviderPortalToken::abilitiesFor(ProviderPortal::provider('transports-alpha')->id))->plainTextToken;
    $headers = ProviderPortal::bearer($token) + ['X-Organization-Id' => authOrganization()->id];

    $this->withHeaders($headers)->getJson('/api/v1/providers')
        ->assertForbidden()->assertJsonPath('message', 'Ce jeton est réservé à un portail externe.');
    $this->withHeaders(ProviderPortal::bearer($token))->getJson('/api/v1/customer/context')
        ->assertForbidden()->assertJsonPath('code', 'customer_access_revoked');
    $this->withHeaders(ProviderPortal::bearer($token))->getJson(PROBE)->assertOk();
});

it('refuse au portail fournisseur les jetons interne et client', function (): void {
    $this->seed(CustomerPortalDemoSeeder::class);

    revoked($this->withHeaders(ProviderPortal::bearer(ProviderPortal::internalToken(authUser())))->getJson(PROBE));
    revoked($this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson(PROBE));
});

it('n’ouvre à un compte client et fournisseur que le portail de chaque jeton', function (): void {
    $this->seed(CustomerPortalDemoSeeder::class);
    $customerMember = CustomerPortal::member('admin@ikea.test');
    ProviderUser::create(['provider_id' => ProviderPortal::provider('transports-beta')->id, 'user_id' => $customerMember->user_id, 'status' => 'active'])
        ->roles()->attach(Role::where('code', ProviderPortalRoles::VIEWER)->where('organization_id', authOrganization()->id)->value('id'), ['id' => (string) str()->ulid()]);
    $providerToken = ProviderPortal::token('admin@ikea.test');

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson('/api/v1/customer/context')->assertOk();
    revoked($this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson(PROBE));
    $this->withHeaders(ProviderPortal::bearer($providerToken))->getJson(PROBE)->assertOk();
    $this->withHeaders(ProviderPortal::bearer($providerToken))->getJson('/api/v1/customer/context')->assertForbidden();
});

it('coupe l’accès à la requête suivante quand un maillon tombe', function (string $link): void {
    $this->withHeaders($this->alpha)->getJson(PROBE)->assertOk();

    match ($link) {
        'compte' => ProviderPortal::member('admin@transports-alpha.test')->user->update(['status' => UserStatus::SUSPENDED]),
        'rattachement' => ProviderPortal::member('admin@transports-alpha.test')->update(['status' => UserStatus::DISABLED]),
        'fournisseur' => ProviderPortal::provider('transports-alpha')->update(['status' => 'blocked']),
        'organisation' => authOrganization()->update(['status' => OrganizationStatus::SUSPENDED]),
        'rôle' => Role::where('code', ProviderPortalRoles::ADMIN)->where('organization_id', authOrganization()->id)->firstOrFail()
            ->permissions()->detach(Permission::where('code', 'provider.portal.access')->value('id')),
    };

    revoked($this->withHeaders(ProviderPortal::headers('admin@transports-alpha.test'))->getJson(PROBE));
})->with([
    'compte suspendu' => ['compte'],
    'rattachement désactivé' => ['rattachement'],
    'fournisseur bloqué' => ['fournisseur'],
    'organisation suspendue' => ['organisation'],
    'rôle sans accès au portail' => ['rôle'],
]);

it('refuse un jeton échu (401)', function (): void {
    $membership = ProviderPortal::member('admin@transports-alpha.test');
    $token = $membership->user->createToken(ProviderPortalToken::NAME, ProviderPortalToken::abilitiesFor($membership->provider_id), now()->subMinute())->plainTextToken;

    $this->withHeaders(ProviderPortal::bearer($token))->getJson(PROBE)->assertUnauthorized();
});
