<?php

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\UserStatus;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Le chemin de celui qui a perdu son mot de passe.
 *
 * **Le formulaire public est réservé aux administrateurs.** Un exploitant ou un
 * chauffeur qui perd son mot de passe le demande au sien, qui le lui rend
 * depuis sa fiche. Le formulaire déclenche un courriel vers une adresse que
 * l'appelant choisit : restreint, il ne concerne plus qu'une poignée de comptes
 * dont on sait qui ils sont.
 *
 * **La réponse ne dit jamais qui est qui.** Ni si l'adresse existe — le
 * formulaire deviendrait un annuaire —, ni si son compte est administrateur :
 * il désignerait alors, dans une liste d'adresses, celles qui ouvrent le plus
 * de portes.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();

    Notification::fake();
});

/**
 * Une organisation semée, et un membre ordinaire dedans : ni propriétaire, ni
 * porteur de `users.reset_password`.
 */
function ordinaryMember(): User
{
    $member = User::factory()->create(['status' => UserStatus::ACTIVE]);

    OrganizationUser::create([
        'organization_id' => authOrganization()->id,
        'user_id' => $member->id,
        'is_owner' => false,
        'is_primary' => true,
        'status' => UserStatus::ACTIVE,
        'joined_at' => now(),
    ]);

    return $member;
}

/**
 * Le compte semé est propriétaire de son organisation : il détient tout chez
 * lui, `users.reset_password` compris.
 */
it('sends the link to an administrator', function (): void {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->user->email])
        ->assertOk();

    Notification::assertSentTo($this->user, ResetPassword::class);
});

/**
 * Un membre ordinaire reçoit la même réponse, et rien d'autre : c'est à son
 * administrateur qu'il s'adresse, depuis un écran qui existe déjà.
 */
it('sends nothing to an ordinary member, and says the same thing', function (): void {
    $member = ordinaryMember();

    givePermissions($role = organizationRole(authOrganization(), 'exploitant'), ['orders.view']);
    giveRoles(authOrganization()->id, $member->id, [$role]);

    $admin = $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->user->email]);
    $ordinary = $this->postJson('/api/v1/auth/forgot-password', ['email' => $member->email]);

    $ordinary->assertOk();
    expect($ordinary->json('data.message'))->toBe($admin->json('data.message'));

    Notification::assertSentTimes(ResetPassword::class, 1);
    Notification::assertNotSentTo($member, ResetPassword::class);
});

/**
 * Le droit de rendre son accès à quelqu'un d'autre est ce qui définit un
 * administrateur ici — pas le nom de son rôle.
 */
it('sends the link to a member who holds users.reset_password', function (): void {
    $member = ordinaryMember();

    givePermissions($role = organizationRole(authOrganization(), 'responsable'), ['users.reset_password']);
    giveRoles(authOrganization()->id, $member->id, [$role]);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $member->email])->assertOk();

    Notification::assertSentTo($member, ResetPassword::class);
});

/**
 * Le membre ordinaire n'est pas privé du chemin : son administrateur le lui
 * ouvre, et le jeton ainsi émis vaut autant que les autres.
 */
it('still honours a link issued by an administrator for an ordinary member', function (): void {
    $member = ordinaryMember();

    $token = Password::broker()->createToken($member);

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $member->email,
        'token' => $token,
        'password' => 'nouveau-mot-de-passe-42',
        'password_confirmation' => 'nouveau-mot-de-passe-42',
    ])->assertOk();
});

it('answers the same for an unknown address, and sends nothing', function (): void {
    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->user->email]);
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'personne@example.test']);

    $unknown->assertOk();
    expect($unknown->json('data.message'))->toBe($known->json('data.message'));

    Notification::assertSentTimes(ResetPassword::class, 1);
});

it('refuses an address that is not one', function (): void {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'pas-une-adresse'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('sets the password with the token, and the new one opens a session', function (): void {
    $token = Password::broker()->createToken($this->user);

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $this->user->email,
        'token' => $token,
        'password' => 'nouveau-mot-de-passe-42',
        'password_confirmation' => 'nouveau-mot-de-passe-42',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'nouveau-mot-de-passe-42',
    ])->assertOk();
});

it('refuses a token that is not the one issued', function (): void {
    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $this->user->email,
        'token' => 'jeton-invente',
        'password' => 'nouveau-mot-de-passe-42',
        'password_confirmation' => 'nouveau-mot-de-passe-42',
    ])->assertStatus(422);
});
