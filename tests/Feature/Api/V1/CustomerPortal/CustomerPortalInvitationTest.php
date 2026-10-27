<?php

use App\Modules\CustomerPortal\Enums\CustomerInvitationStatus;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Models\CustomerUserInvitation;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CustomerPortal;

/**
 * Invitation → courriel → activation → connexion.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    Mail::fake();
    $this->admin = CustomerPortal::headers('admin@ikea.test');
});

function inviteToIkea(array $payload)
{
    return test()->withHeaders(test()->admin)->postJson('/api/v1/customer/users/invite', $payload);
}

function activateInvitation(string $token, array $extra = [])
{
    return test()->postJson('/api/v1/customer/auth/activate', ['token' => $token, ...$extra]);
}

const PORTAL_NEW_PASSWORD = 'Motdepasse-Solide-2026!';

it('creates an invited account, stores only the token hash and mails the link', function (): void {
    inviteToIkea(['email' => 'Nina@IKEA.test', 'firstName' => 'Nina'])->assertCreated()
        ->assertJsonPath('data.status', 'invited');

    $user = User::where('email', 'nina@ikea.test')->firstOrFail();
    $token = CustomerPortal::mailedToken('nina@ikea.test');
    $invitation = CustomerUserInvitation::where('email', 'nina@ikea.test')->firstOrFail();

    expect($user->status)->toBe(UserStatus::INVITED)
        ->and($invitation->token_hash)->toBe(hash('sha256', $token))
        ->and(CustomerUserInvitation::where('token_hash', $token)->exists())->toBeFalse();
});

it('activates a new account with a password, which then logs in', function (): void {
    inviteToIkea(['email' => 'nina@ikea.test']);
    $token = CustomerPortal::mailedToken('nina@ikea.test');

    $this->getJson("/api/v1/customer/auth/invitation?token={$token}")->assertOk()
        ->assertJsonPath('data.requiresPassword', true)
        ->assertJsonPath('data.customerName', 'IKEA');

    activateInvitation($token)->assertUnprocessable()->assertJsonValidationErrors('password');
    activateInvitation($token, ['firstName' => 'Nina', 'lastName' => 'Berg', 'password' => PORTAL_NEW_PASSWORD, 'password_confirmation' => PORTAL_NEW_PASSWORD])->assertOk();

    expect(CustomerPortal::member('nina@ikea.test')->status)->toBe(UserStatus::ACTIVE);
    $this->postJson('/api/v1/customer/auth/login', ['email' => 'nina@ikea.test', 'password' => PORTAL_NEW_PASSWORD])->assertOk();
});

it('refuses a token that was already used', function (): void {
    inviteToIkea(['email' => 'nina@ikea.test']);
    $token = CustomerPortal::mailedToken('nina@ikea.test');
    $payload = ['password' => PORTAL_NEW_PASSWORD, 'password_confirmation' => PORTAL_NEW_PASSWORD];

    activateInvitation($token, $payload)->assertOk();
    activateInvitation($token, $payload)->assertUnprocessable()->assertJsonValidationErrors('token');
});

it('refuses an expired invitation', function (): void {
    inviteToIkea(['email' => 'nina@ikea.test']);
    $token = CustomerPortal::mailedToken('nina@ikea.test');

    $this->travel(config('customer_portal.invitation_expires_hours') + 1)->hours();

    activateInvitation($token, ['password' => PORTAL_NEW_PASSWORD, 'password_confirmation' => PORTAL_NEW_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
    expect(CustomerPortal::member('nina@ikea.test')->status)->toBe(UserStatus::INVITED);
});

it('resends an invitation, which voids the previous link', function (): void {
    inviteToIkea(['email' => 'nina@ikea.test']);
    $first = CustomerPortal::mailedToken('nina@ikea.test');
    $member = CustomerPortal::member('nina@ikea.test');

    $this->withHeaders($this->admin)->postJson("/api/v1/customer/users/{$member->id}/resend-invitation")->assertOk();
    $second = CustomerPortal::mailedToken('nina@ikea.test');

    expect($second)->not->toBe($first)
        ->and(CustomerUserInvitation::where('token_hash', hash('sha256', $first))->value('status'))
        ->toBe(CustomerInvitationStatus::CANCELLED);

    $payload = ['password' => PORTAL_NEW_PASSWORD, 'password_confirmation' => PORTAL_NEW_PASSWORD];
    activateInvitation($first, $payload)->assertUnprocessable();
    activateInvitation($second, $payload)->assertOk();
});

it('refuses to resend to an already active member', function (): void {
    $this->withHeaders($this->admin)
        ->postJson('/api/v1/customer/users/'.CustomerPortal::member('viewer@ikea.test')->id.'/resend-invitation')
        ->assertUnprocessable();
});

it('reuses an existing User without duplicating it or touching its password', function (): void {
    $existing = User::where('email', 'admin@qoqa.test')->firstOrFail();
    $hash = $existing->password;

    inviteToIkea(['email' => 'admin@qoqa.test'])->assertCreated();
    $token = CustomerPortal::mailedToken('admin@qoqa.test');

    $this->getJson("/api/v1/customer/auth/invitation?token={$token}")->assertJsonPath('data.requiresPassword', false);
    activateInvitation($token, ['password' => PORTAL_NEW_PASSWORD, 'password_confirmation' => PORTAL_NEW_PASSWORD])->assertOk();

    expect(User::where('email', 'admin@qoqa.test')->count())->toBe(1)
        ->and($existing->fresh()->password)->toBe($hash)
        ->and(Hash::check(CustomerPortal::PASSWORD, $existing->fresh()->password))->toBeTrue()
        ->and(CustomerUser::where('user_id', $existing->id)->count())->toBe(2);
});

it('refuses to invite an address that is already a member', function (): void {
    inviteToIkea(['email' => 'viewer@ikea.test'])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('lets the carrier invite the first customer admin, as owner', function (): void {
    $customer = CustomerPortal::customer('ikea');
    CustomerUser::where('customer_id', $customer->id)->delete();

    $this->actingAs(authUser(), 'sanctum')
        ->withHeaders(['X-Organization-Id' => authOrganization()->id])
        ->postJson("/api/v1/customers/{$customer->id}/portal-users/invite", ['email' => 'chef@ikea.test'])
        ->assertCreated()
        ->assertJsonPath('data.isOwner', true)
        ->assertJsonPath('data.roles.0.code', 'CUSTOMER_ADMIN');
});
