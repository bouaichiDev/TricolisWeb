<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Models\CustomerUserRole;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\IssueCustomerInvitation;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invite une adresse au portail d'un client.
 *
 * **Un compte existant est réutilisé**, jamais dupliqué : si l'adresse est
 * déjà un `User` (collaborateur d'un transporteur, membre d'un autre client),
 * seule l'appartenance est créée, et son mot de passe reste le sien.
 *
 * Sinon, le compte naît au statut `invited`, avec un mot de passe aléatoire
 * que personne ne connaît : il ne peut se connecter nulle part tant que
 * l'invitation n'a pas été acceptée.
 *
 * Le rôle éventuel est vérifié par l'appelant (portée, organisation, plafond
 * de délégation) : cette action se contente de l'attacher.
 */
final readonly class InviteCustomerUser
{
    public function __construct(
        private IssueCustomerInvitation $invitations,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @param  array{email: string, firstName?: string|null, lastName?: string|null}  $data
     */
    public function execute(
        Customer $customer,
        array $data,
        ?Role $role,
        ?User $inviter,
        bool $isOwner = false,
        ?string $ipAddress = null,
    ): CustomerUser {
        $email = Str::lower(trim($data['email']));

        $membership = DB::transaction(function () use ($customer, $data, $email, $role, $inviter, $isOwner): CustomerUser {
            $user = User::where('email', $email)->lockForUpdate()->first()
                ?? $this->createInvitedUser($email, $data);

            $this->ensureNotAlreadyMember($customer, $user);

            $membership = CustomerUser::create([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'status' => UserStatus::INVITED,
                'is_owner' => $isOwner,
                'invited_by' => $inviter?->id,
                'invited_at' => now(),
            ]);

            if ($role !== null) {
                CustomerUserRole::create(['customer_user_id' => $membership->id, 'role_id' => $role->id]);
            }

            $this->invitations->execute($membership, $role?->id, $inviter);

            return $membership;
        });

        $this->audit->record($customer, $inviter, 'user_invited', $membership, null, [
            'email' => $email,
            'roleCode' => $role?->code,
            'isOwner' => $isOwner,
        ], $ipAddress);

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

    /**
     * Refuse une seconde appartenance au même client, en disant quoi faire.
     */
    private function ensureNotAlreadyMember(Customer $customer, User $user): void
    {
        $existing = CustomerUser::where('customer_id', $customer->id)->where('user_id', $user->id)->first();

        if ($existing === null) {
            return;
        }

        $message = match ($existing->status) {
            UserStatus::INVITED => 'Une invitation est déjà en attente pour cette adresse : renvoyez-la plutôt.',
            UserStatus::ACTIVE => 'Cette adresse est déjà membre de ce portail client.',
            default => 'Cette adresse est déjà membre, mais désactivée : réactivez-la plutôt.',
        };

        throw ValidationException::withMessages(['email' => [$message]]);
    }
}
