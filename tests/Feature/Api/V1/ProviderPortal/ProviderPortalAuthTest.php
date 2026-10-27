<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\Role;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Models\ProviderUserRole;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Modules\ProviderPortal\Support\ProviderPortalToken;
use Database\Seeders\DemoProviderPortalSeeder;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\ProviderPortal;

/**
 * Authentification du portail fournisseur : /api/v1/provider/auth/*.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
});

function providerLogin(string $email, string $password = ProviderPortal::PASSWORD, array $extra = [])
{
    return test()->postJson('/api/v1/provider/auth/login', ['email' => $email, 'password' => $password, ...$extra]);
}

const NO_ACCESS = 'Ce compte n’a pas d’accès actif au portail fournisseur.';

it('connecte un membre et rend le contexte du fournisseur', function (): void {
    $response = providerLogin('admin@transports-alpha.test')->assertOk()
        ->assertJsonPath('data.context.provider.code', 'transports-alpha')
        ->assertJsonPath('data.context.user.email', 'admin@transports-alpha.test')
        ->assertJsonPath('data.context.roles.0.code', ProviderPortalRoles::ADMIN);

    expect($response->json('data.context.permissions'))->toContain('provider.portal.access');

    $token = PersonalAccessToken::findToken($response->json('data.token'));
    expect($token->abilities)->toBe(ProviderPortalToken::abilitiesFor(ProviderPortal::provider('transports-alpha')->id))
        ->and($token->expires_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'provider_portal.login')->where('user_id', $token->tokenable_id)->exists())->toBeTrue()
        ->and(ProviderPortal::member('admin@transports-alpha.test')->user->last_login_at)->not->toBeNull();

    $this->withHeaders(ProviderPortal::bearer($response->json('data.token')))->getJson('/api/v1/provider/auth/me')
        ->assertOk()->assertJsonPath('data.provider.code', 'transports-alpha');
});

it('refuse un mauvais mot de passe', function (): void {
    providerLogin('admin@transports-alpha.test', 'wrong-password')->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('bloque après cinq essais', function (): void {
    foreach (range(1, 5) as $attempt) {
        providerLogin('admin@transports-alpha.test', 'wrong-password');
    }

    expect(providerLogin('admin@transports-alpha.test')->assertUnprocessable()->json('errors.email.0'))->toContain('Trop de tentatives');
});

it('refuse un compte sans accès fournisseur actif, sans dire pourquoi', function (string $case): void {
    $email = match ($case) {
        'interne' => authUser()->email,
        'fournisseur inactif' => tap('admin@transports-alpha.test', fn () => ProviderPortal::provider('transports-alpha')->update(['status' => 'inactive'])),
        'rattachement invité' => tap('admin@transports-alpha.test', fn () => ProviderPortal::member('admin@transports-alpha.test')->update(['status' => 'invited'])),
    };

    expect(providerLogin($email)->assertUnprocessable()->json('errors.email.0'))->toBe(NO_ACCESS);
})->with(['interne', 'fournisseur inactif', 'rattachement invité']);

it('demande de choisir quand le compte sert deux fournisseurs, puis ouvre celui choisi', function (): void {
    $beta = ProviderPortal::provider('transports-beta');
    $membership = ProviderUser::create(['provider_id' => $beta->id, 'user_id' => ProviderPortal::member('admin@transports-alpha.test')->user_id, 'status' => 'active']);
    ProviderUserRole::create(['provider_user_id' => $membership->id, 'role_id' => Role::where('organization_id', $beta->organization_id)->where('code', ProviderPortalRoles::VIEWER)->value('id')]);

    providerLogin('admin@transports-alpha.test')->assertStatus(409)
        ->assertJsonPath('code', 'provider_selection_required')
        ->assertJsonCount(2, 'providers');

    $token = providerLogin('admin@transports-alpha.test', extra: ['providerId' => $beta->id])->assertOk()->json('data.token');

    expect(PersonalAccessToken::findToken($token)->abilities)->toBe(ProviderPortalToken::abilitiesFor($beta->id));
});

it('refuse un fournisseur choisi auquel le compte n’est pas rattaché', function (): void {
    providerLogin('admin@transports-alpha.test', extra: ['providerId' => ProviderPortal::provider('transports-beta')->id])
        ->assertUnprocessable()->assertJsonValidationErrors('providerId');
});

it('ne révoque à la déconnexion que le jeton de la requête', function (): void {
    $first = ProviderPortal::token('admin@transports-alpha.test');
    $second = ProviderPortal::token('admin@transports-alpha.test');

    $this->withHeaders(ProviderPortal::bearer($first))->postJson('/api/v1/provider/auth/logout')->assertNoContent();

    $this->withHeaders(ProviderPortal::bearer($first))->getJson('/api/v1/provider/auth/me')->assertUnauthorized();
    $this->withHeaders(ProviderPortal::bearer($second))->getJson('/api/v1/provider/auth/me')->assertOk();
});
