<?php

use App\Http\Middleware\EnsureCustomerPortalContext;
use App\Modules\CustomerPortal\Support\CustomerPortalToken;
use App\Shared\Enums\OrganizationStatus;
use Database\Seeders\CustomerPortalDemoSeeder;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\CustomerPortal;

/**
 * Sessions du portail (Phase 7, §11-§12) : jeton à durée de vie, organisation
 * suspendue ou fermée, déconnexion effective.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->organization = CustomerPortal::customer('ikea')->organization;
    $this->login = fn () => $this->postJson('/api/v1/customer/auth/login', ['email' => 'admin@ikea.test', 'password' => CustomerPortal::PASSWORD]);
});

it('émet un jeton qui expire selon la configuration', function (): void {
    config(['customer_portal.token_ttl_minutes' => 90]);

    ($this->login)()->assertOk();
    $token = PersonalAccessToken::where('name', CustomerPortalToken::NAME)->latest('id')->firstOrFail();

    expect($token->expires_at)->not->toBeNull()
        ->and(abs($token->expires_at->diffInMinutes(now()->addMinutes(90))))->toBeLessThan(2);
});

it('refuse un jeton échu, sans autre détail qu’une session expirée', function (): void {
    $plain = ($this->login)()->json('data.token');
    PersonalAccessToken::where('name', CustomerPortalToken::NAME)->update(['expires_at' => now()->subMinute()]);
    app('auth')->forgetGuards();

    $this->withHeaders(['Authorization' => "Bearer {$plain}"])->getJson('/api/v1/customer/context')->assertUnauthorized();
});

it('ferme le portail d’une organisation suspendue ou fermée, à la requête suivante et à la connexion', function (OrganizationStatus $status): void {
    $headers = CustomerPortal::headers('admin@ikea.test');
    $this->organization->update(['status' => $status]);

    $this->withHeaders($headers)->getJson('/api/v1/customer/orders')
        ->assertForbidden()
        ->assertJsonPath('code', EnsureCustomerPortalContext::REVOKED);
    app('auth')->forgetGuards();
    ($this->login)()->assertUnprocessable();
})->with([OrganizationStatus::SUSPENDED, OrganizationStatus::CLOSED]);

it('laisse ouvert le portail d’une organisation en cours d’ouverture', function (): void {
    $this->organization->update(['status' => OrganizationStatus::PENDING]);

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson('/api/v1/customer/context')->assertOk();
});

it('ne rend plus rien avec un jeton déconnecté', function (): void {
    $plain = ($this->login)()->json('data.token');
    app('auth')->forgetGuards();
    $this->withHeaders(['Authorization' => "Bearer {$plain}"])->postJson('/api/v1/customer/auth/logout')->assertNoContent();
    app('auth')->forgetGuards();

    $this->withHeaders(['Authorization' => "Bearer {$plain}"])->getJson('/api/v1/customer/context')->assertUnauthorized();
});

it('refuse un jeton forgé pour un autre client que celui de son compte', function (): void {
    $member = CustomerPortal::member('admin@ikea.test');
    $forged = $member->user->createToken(CustomerPortalToken::NAME, CustomerPortalToken::abilitiesFor(CustomerPortal::customer('qoqa')->id))->plainTextToken;

    $this->withHeaders(CustomerPortal::bearer($forged))->getJson('/api/v1/customer/context')
        ->assertForbidden()
        ->assertJsonPath('code', EnsureCustomerPortalContext::REVOKED);
});

it('freine les connexions répétées depuis une même adresse', function (): void {
    config(['customer_portal.rate_limits.logins_per_minute' => 3]);

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/v1/customer/auth/login', ['email' => "inconnu{$attempt}@example.test", 'password' => 'x'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/customer/auth/login', ['email' => 'admin@ikea.test', 'password' => CustomerPortal::PASSWORD])->assertTooManyRequests();
});
