<?php

use App\Modules\ProviderPortal\Mail\ProviderPortalMail;
use App\Modules\ProviderPortal\Models\ProviderUserInvitation;
use App\Shared\Enums\UserStatus;
use Database\Seeders\DemoProviderPortalSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ProviderPortal;

/**
 * Invitation (back-office) → courriel → activation → connexion ; et mot de
 * passe oublié, côté portail fournisseur.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    Mail::fake();
});

const PROVIDER_NEW_PASSWORD = 'Motdepasse-Solide-2026!';

function inviteToAlpha(string $email): string
{
    $alpha = ProviderPortal::provider('transports-alpha');
    test()->actingAs(authUser())->withHeaders(['X-Organization-Id' => authOrganization()->id])
        ->postJson("/api/v1/providers/{$alpha->id}/portal-users/invite", ['email' => $email])->assertCreated();
    app('auth')->forgetGuards();

    $mail = Mail::sent(ProviderPortalMail::class, static fn (ProviderPortalMail $mail): bool => $mail->hasTo($email))->last();
    parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

    return (string) $query['token'];
}

it('présente l’invitation puis active le compte, qui peut ensuite se connecter', function (): void {
    $token = inviteToAlpha('nina@alpha.test');

    $this->getJson('/api/v1/provider/auth/invitation?token='.$token)->assertOk()
        ->assertJsonPath('data.providerName', 'Transports Alpha')
        ->assertJsonPath('data.requiresPassword', true);

    $this->postJson('/api/v1/provider/auth/activate', ['token' => $token, 'firstName' => 'Nina', 'password' => PROVIDER_NEW_PASSWORD, 'password_confirmation' => PROVIDER_NEW_PASSWORD])
        ->assertOk();

    expect(ProviderPortal::member('nina@alpha.test')->status)->toBe(UserStatus::ACTIVE);
    $this->postJson('/api/v1/provider/auth/login', ['email' => 'nina@alpha.test', 'password' => PROVIDER_NEW_PASSWORD])->assertOk();
});

it('refuse un lien inconnu, déjà utilisé ou expiré', function (string $case): void {
    $token = inviteToAlpha('nina@alpha.test');
    $invitation = ProviderUserInvitation::where('token_hash', ProviderUserInvitation::hashToken($token))->firstOrFail();

    match ($case) {
        'inconnu' => $token = 'not-a-real-token',
        'utilisé' => $invitation->update(['status' => 'accepted']),
        'expiré' => $invitation->update(['expires_at' => now()->subMinute()]),
    };

    $this->postJson('/api/v1/provider/auth/activate', ['token' => $token, 'password' => PROVIDER_NEW_PASSWORD, 'password_confirmation' => PROVIDER_NEW_PASSWORD])
        ->assertUnprocessable()->assertJsonValidationErrors('token');
})->with(['inconnu', 'utilisé', 'expiré']);

it('répond pareil à « mot de passe oublié », l’adresse soit connue ou non', function (): void {
    $known = $this->postJson('/api/v1/provider/auth/forgot-password', ['email' => 'admin@transports-alpha.test'])->assertOk()->json('data.message');
    $unknown = $this->postJson('/api/v1/provider/auth/forgot-password', ['email' => 'personne@nulle-part.test'])->assertOk()->json('data.message');

    expect($known)->toBe($unknown);
    Mail::assertSent(ProviderPortalMail::class, 1);
    Mail::assertSent(ProviderPortalMail::class, fn (ProviderPortalMail $mail): bool => $mail->kind === ProviderPortalMail::PASSWORD_RESET
        && str_starts_with($mail->actionUrl, config('provider_portal.url').'/reset-password?token='));
});

it('réinitialise le mot de passe et révoque les jetons du portail fournisseur', function (): void {
    $open = ProviderPortal::token('admin@transports-alpha.test');
    $this->postJson('/api/v1/provider/auth/forgot-password', ['email' => 'admin@transports-alpha.test']);
    $mail = Mail::sent(ProviderPortalMail::class)->last();
    parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

    $this->postJson('/api/v1/provider/auth/reset-password', [
        'email' => 'admin@transports-alpha.test', 'token' => $query['token'],
        'password' => PROVIDER_NEW_PASSWORD, 'password_confirmation' => PROVIDER_NEW_PASSWORD,
    ])->assertOk();

    $this->withHeaders(ProviderPortal::bearer($open))->getJson('/api/v1/provider/auth/me')->assertUnauthorized();
    $this->postJson('/api/v1/provider/auth/login', ['email' => 'admin@transports-alpha.test', 'password' => PROVIDER_NEW_PASSWORD])->assertOk();
});
