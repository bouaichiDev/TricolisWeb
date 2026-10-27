<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\ProviderPortal\Mail\ProviderPortalMail;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Models\ProviderUserInvitation;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Shared\Enums\UserStatus;
use Database\Seeders\DemoProviderPortalSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ProviderPortal;

/**
 * Le transporteur gère, depuis le back-office et lui seul, les accès au
 * portail de ses fournisseurs.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    Mail::fake();
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $this->base = "/api/v1/providers/{$this->alpha->id}/portal-users";
    $this->internal = ['X-Organization-Id' => authOrganization()->id];
    $this->actingAs(authUser());
});

function mailedProviderToken(string $email): string
{
    $mail = Mail::sent(ProviderPortalMail::class, static fn (ProviderPortalMail $mail): bool => $mail->hasTo($email))->last();
    parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

    return (string) $query['token'];
}

function providerRole(string $code): Role
{
    return Role::where('organization_id', authOrganization()->id)->where('code', $code)->firstOrFail();
}

it('invite un nouveau compte : rattachement invité, rôle, lien vers le portail fournisseur', function (): void {
    $this->withHeaders($this->internal)->postJson("{$this->base}/invite", ['email' => 'Nina@Alpha.test', 'firstName' => 'Nina', 'roleId' => providerRole(ProviderPortalRoles::VIEWER)->id])
        ->assertCreated()
        ->assertJsonPath('data.status', 'invited')
        ->assertJsonPath('data.roles.0.code', ProviderPortalRoles::VIEWER);

    $token = mailedProviderToken('nina@alpha.test');
    $mail = Mail::sent(ProviderPortalMail::class)->last();

    expect($mail->actionUrl)->toStartWith(config('provider_portal.url').'/activate-account?token=')
        ->and(ProviderUserInvitation::where('token_hash', ProviderUserInvitation::hashToken($token))->exists())->toBeTrue()
        ->and(ProviderUserInvitation::where('token_hash', $token)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'provider_portal.user_invited')->exists())->toBeTrue();
});

it('rattache un compte existant sans toucher à son mot de passe, rôle administrateur par défaut', function (): void {
    $existing = ProviderPortal::member('admin@transports-beta.test')->user;
    $hash = $existing->password;

    $this->withHeaders($this->internal)->postJson("{$this->base}/invite", ['email' => $existing->email])
        ->assertCreated()->assertJsonPath('data.roles.0.code', ProviderPortalRoles::ADMIN);

    expect($existing->fresh()->password)->toBe($hash)
        ->and(User::where('email', $existing->email)->count())->toBe(1);
});

it('refuse un second rattachement de la même adresse au même fournisseur', function (): void {
    $this->withHeaders($this->internal)->postJson("{$this->base}/invite", ['email' => 'admin@transports-alpha.test'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(ProviderUser::forProvider($this->alpha->id)->count())->toBe(2);
});

it('refuse un rôle qui n’est pas un rôle fournisseur de l’organisation', function (string $code): void {
    $this->withHeaders($this->internal)->postJson("{$this->base}/invite", ['email' => 'x@alpha.test', 'roleId' => Role::where('code', $code)->value('id')])
        ->assertUnprocessable()->assertJsonValidationErrors('roleId');
})->with(['admin', 'CUSTOMER_ADMIN']);

it('refuse les champs que le serveur impose', function (): void {
    $this->withHeaders($this->internal)->postJson("{$this->base}/invite", ['email' => 'x@alpha.test', 'providerId' => ProviderPortal::provider('transports-beta')->id, 'status' => 'active'])
        ->assertUnprocessable()->assertJsonValidationErrors(['providerId', 'status']);
});

it('ne touche jamais le membre d’un autre fournisseur par l’adresse d’Alpha', function (): void {
    $beta = ProviderPortal::member('admin@transports-beta.test');

    $this->withHeaders($this->internal)->postJson("{$this->base}/{$beta->id}/disable")->assertNotFound();

    expect($beta->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('réserve la gestion à `providers.update` et la ferme aux jetons de portail', function (): void {
    $reader = organizationRole(authOrganization(), 'lecteur-fournisseurs');
    givePermissions($reader, ['providers.view']);
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    OrganizationUser::create(['organization_id' => authOrganization()->id, 'user_id' => $user->id, 'is_owner' => false, 'is_primary' => true, 'status' => UserStatus::ACTIVE, 'joined_at' => now()]);
    giveRoles(authOrganization()->id, $user->id, [$reader]);
    $this->actingAs($user);

    $this->withHeaders($this->internal)->getJson($this->base)->assertOk();
    $this->withHeaders($this->internal)->postJson("{$this->base}/invite", ['email' => 'x@alpha.test'])->assertForbidden();

    $this->withHeaders(ProviderPortal::headers('admin@transports-alpha.test') + $this->internal)->getJson($this->base)->assertForbidden();
});

it('désactive : l’accès tombe aussitôt et les jetons du fournisseur sont révoqués ; réactive ensuite', function (): void {
    $member = ProviderPortal::member('viewer@transports-alpha.test');
    $token = ProviderPortal::token('viewer@transports-alpha.test');

    $this->withHeaders($this->internal)->postJson("{$this->base}/{$member->id}/disable")->assertOk()->assertJsonPath('data.status', 'disabled');
    $this->withHeaders(ProviderPortal::bearer($token))->getJson('/api/v1/provider/auth/me')->assertUnauthorized();

    $this->actingAs(authUser());
    $this->withHeaders($this->internal)->postJson("{$this->base}/{$member->id}/enable")->assertOk()->assertJsonPath('data.status', 'active');
    expect(AuditLog::whereIn('action', ['provider_portal.user_disabled', 'provider_portal.user_enabled'])->count())->toBe(2);
});

it('liste les seuls rôles fournisseur de l’organisation, avec `providers.view`', function (): void {
    $codes = collect($this->withHeaders($this->internal)->getJson("/api/v1/providers/{$this->alpha->id}/portal-roles")->assertOk()->json('data'))->pluck('code');

    expect($codes->sort()->values()->all())->toBe(['PROVIDER_ADMIN', 'PROVIDER_DISPATCHER', 'PROVIDER_FLEET_MANAGER', 'PROVIDER_VIEWER']);
});

it('change le rôle d’un membre', function (): void {
    $member = ProviderPortal::member('viewer@transports-alpha.test');

    $this->withHeaders($this->internal)->patchJson("{$this->base}/{$member->id}", ['roleId' => providerRole(ProviderPortalRoles::DISPATCHER)->id])
        ->assertOk()->assertJsonPath('data.roles.0.code', ProviderPortalRoles::DISPATCHER)->assertJsonCount(1, 'data.roles');
});

it('renvoie une invitation : l’ancien lien cesse de valoir ; refusé pour un compte actif', function (): void {
    $this->withHeaders($this->internal)->postJson("{$this->base}/invite", ['email' => 'nina@alpha.test'])->assertCreated();
    $first = mailedProviderToken('nina@alpha.test');
    $member = ProviderUser::whereHas('user', fn ($q) => $q->where('email', 'nina@alpha.test'))->firstOrFail();

    $this->withHeaders($this->internal)->postJson("{$this->base}/{$member->id}/resend-invitation")->assertOk();

    expect(mailedProviderToken('nina@alpha.test'))->not->toBe($first)
        ->and(ProviderUserInvitation::where('token_hash', ProviderUserInvitation::hashToken($first))->value('status')->value)->toBe('cancelled');

    $active = ProviderPortal::member('viewer@transports-alpha.test');
    $this->withHeaders($this->internal)->postJson("{$this->base}/{$active->id}/resend-invitation")->assertUnprocessable();
    expect(Hash::check('password', $active->user->password))->toBeTrue();
});
