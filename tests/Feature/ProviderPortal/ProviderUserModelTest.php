<?php

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Models\ProviderUserInvitation;
use App\Modules\ProviderPortal\Models\ProviderUserRole;
use App\Modules\Providers\Models\Provider;
use App\Shared\Enums\RoleScope;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\QueryException;

/**
 * Le rattachement d'un compte au portail d'un fournisseur : une ligne par
 * couple (fournisseur, compte), un statut, des rôles du RBAC partagé.
 */
beforeEach(function (): void {
    $this->seed();
    $this->provider = Provider::where('code', 'demo-provider')->firstOrFail();
    $this->user = User::create([
        'first_name' => 'Léa', 'last_name' => 'Martin', 'email' => 'lea@transports.test',
        'password' => 'secret-password-1', 'status' => UserStatus::ACTIVE->value,
    ]);
});

it('refuse un second rattachement du même compte au même fournisseur', function (): void {
    ProviderUser::create(['provider_id' => $this->provider->id, 'user_id' => $this->user->id, 'status' => 'active']);

    expect(fn () => ProviderUser::create(['provider_id' => $this->provider->id, 'user_id' => $this->user->id, 'status' => 'invited']))
        ->toThrow(QueryException::class);
});

it('n’est actif que dans l’état actif', function (string $status, bool $active): void {
    $membership = ProviderUser::create(['provider_id' => $this->provider->id, 'user_id' => $this->user->id, 'status' => $status]);

    expect($membership->isActive())->toBe($active);
})->with([['active', true], ['invited', false], ['suspended', false], ['disabled', false]]);

it('porte ses rôles par la table provider_user_roles', function (): void {
    $membership = ProviderUser::create(['provider_id' => $this->provider->id, 'user_id' => $this->user->id, 'status' => 'active']);
    $role = Role::create([
        'organization_id' => $this->provider->organization_id, 'code' => 'PROVIDER_TEST', 'name' => 'Test',
        'scope' => RoleScope::PROVIDER->value, 'is_system' => true, 'status' => 'active',
    ]);
    ProviderUserRole::create(['provider_user_id' => $membership->id, 'role_id' => $role->id]);

    expect($membership->roles()->pluck('roles.id')->all())->toBe([$role->id])
        ->and(ProviderUser::forProvider($this->provider->id)->count())->toBe(1);
});

it('ne garde que l’empreinte du jeton d’invitation', function (): void {
    expect(ProviderUserInvitation::hashToken('abc'))->toBe(hash('sha256', 'abc'));

    $invitation = ProviderUserInvitation::create([
        'provider_id' => $this->provider->id, 'email' => 'lea@transports.test',
        'token_hash' => ProviderUserInvitation::hashToken('abc'), 'status' => 'pending', 'expires_at' => now()->subMinute(),
    ]);

    expect($invitation->isExpired())->toBeTrue()
        ->and($invitation->toArray())->not->toHaveKey('token_hash');
});
