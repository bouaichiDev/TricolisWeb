<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Models\ProviderUserRole;
use App\Modules\ProviderPortal\Services\IssueProviderInvitation;
use App\Modules\ProviderPortal\Services\ProviderPortalAudit;
use App\Modules\Providers\Models\Provider;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invite une adresse au portail d'un fournisseur — depuis le back-office.
 *
 * **Un compte existant est réutilisé**, jamais dupliqué : seule l'appartenance
 * est créée, et son mot de passe reste le sien. Sinon, le compte naît
 * `invited`, avec un mot de passe aléatoire que personne ne connaît.
 *
 * Le rôle est vérifié par l'appelant (portée `provider`, même organisation).
 */
final readonly class InviteProviderUser
{
    public function __construct(
        private IssueProviderInvitation $invitations,
        private ProviderPortalAudit $audit,
    ) {}

    /**
     * @param  array{email: string, firstName?: string|null, lastName?: string|null}  $data
     */
    public function execute(Provider $provider, array $data, Role $role, ?User $inviter, ?string $ipAddress = null): ProviderUser
    {
        $email = Str::lower(trim($data['email']));

        $membership = DB::transaction(function () use ($provider, $data, $email, $role, $inviter): ProviderUser {
            $user = User::where('email', $email)->lockForUpdate()->first()
                ?? $this->createInvitedUser($email, $data);

            $this->ensureNotAlreadyMember($provider, $user);

            $membership = ProviderUser::create([
                'provider_id' => $provider->id,
                'user_id' => $user->id,
                'status' => UserStatus::INVITED,
                'invited_by' => $inviter?->id,
                'invited_at' => now(),
            ]);

            ProviderUserRole::create(['provider_user_id' => $membership->id, 'role_id' => $role->id]);
            $this->invitations->execute($membership, $role->id, $inviter);

            return $membership;
        });

        $this->audit->record($provider, $inviter, 'user_invited', $membership, null, ['email' => $email, 'roleCode' => $role->code], $ipAddress);

        return $membership->load(['user', 'roles']);
    }

    /**
     * @param  array{firstName?: string|null, lastName?: string|null}  $data
     */
    private function createInvitedUser(string $email, array $data): User
    {
        return User::create([
            'first_name' => trim((string) ($data['firstName'] ?? '')),
            'last_name' => trim((string) ($data['lastName'] ?? '')),
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'status' => UserStatus::INVITED,
        ]);
    }

    private function ensureNotAlreadyMember(Provider $provider, User $user): void
    {
        $existing = ProviderUser::forProvider($provider->id)->where('user_id', $user->id)->first();

        if ($existing === null) {
            return;
        }

        $message = match ($existing->status) {
            UserStatus::INVITED => 'Une invitation est déjà en attente pour cette adresse : renvoyez-la plutôt.',
            UserStatus::ACTIVE => 'Cette adresse a déjà accès au portail de ce fournisseur.',
            default => 'Cette adresse a déjà un accès, désactivé : réactivez-le plutôt.',
        };

        throw ValidationException::withMessages(['email' => [$message]]);
    }
}
