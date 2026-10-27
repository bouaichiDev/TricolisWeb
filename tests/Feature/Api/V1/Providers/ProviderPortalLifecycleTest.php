<?php

use App\Modules\Identity\Models\Role;
use App\Modules\ProviderPortal\Mail\ProviderPortalMail;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Shared\Enums\RoleScope;
use App\Shared\Enums\UserStatus;
use Database\Seeders\DemoProviderPortalSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ProviderPortal;

/**
 * Le cycle de vie des accès autour du fournisseur : une invitation retirée ne
 * s'active plus, une organisation nouvelle peut inviter, et un fournisseur
 * qui a des accès ne se supprime pas en erreur 500.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    Mail::fake();
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $this->internal = ['X-Organization-Id' => authOrganization()->id];
    $this->actingAs(authUser());
});

it('rend inutilisable le lien d’une invitation désactivée', function (): void {
    $this->withHeaders($this->internal)->postJson("/api/v1/providers/{$this->alpha->id}/portal-users/invite", ['email' => 'erreur@alpha.test'])->assertCreated();
    $mail = Mail::sent(ProviderPortalMail::class)->last();
    parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
    $member = ProviderUser::whereHas('user', fn ($q) => $q->where('email', 'erreur@alpha.test'))->firstOrFail();

    $this->withHeaders($this->internal)->postJson("/api/v1/providers/{$this->alpha->id}/portal-users/{$member->id}/disable")->assertOk();
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/provider/auth/activate', ['token' => $query['token'], 'password' => 'Motdepasse-Solide-2026!', 'password_confirmation' => 'Motdepasse-Solide-2026!'])
        ->assertUnprocessable()->assertJsonValidationErrors('token');

    expect($member->fresh()->status)->toBe(UserStatus::DISABLED);
});

it('refuse de supprimer un fournisseur qui a des accès au portail (409, pas 500)', function (): void {
    $this->withHeaders($this->internal)->deleteJson("/api/v1/providers/{$this->alpha->id}")->assertStatus(409);

    expect($this->alpha->fresh())->not->toBeNull();
});

it('donne les rôles du portail fournisseur à une organisation ouverte après le semis', function (string $how): void {
    $organizationId = match ($how) {
        'inscription' => $this->postJson('/api/v1/auth/register', [
            'firstName' => 'Sara', 'lastName' => 'Amrani', 'email' => 'sara@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'organization' => ['name' => 'Atlas Transport', 'timezone' => 'Africa/Casablanca', 'currencyCode' => 'MAD'],
        ])->assertCreated()->json('data.organization.id'),
        'plateforme' => $this->actingAs(makePlatformAdmin(authUser()), 'sanctum')
            ->postJson('/api/v1/organizations', ['code' => 'new-org', 'name' => 'Nouvelle organisation'])
            ->assertCreated()->json('data.id'),
    };

    expect(Role::where('organization_id', $organizationId)->where('scope', RoleScope::PROVIDER->value)->count())->toBe(4);
})->with(['inscription', 'plateforme']);
