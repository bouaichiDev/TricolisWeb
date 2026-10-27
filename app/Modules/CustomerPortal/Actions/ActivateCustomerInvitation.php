<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Enums\CustomerInvitationStatus;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\InvitationLookup;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Accepte une invitation : le compte devient actif, l'appartenance aussi.
 *
 * Deux cas, selon le compte derrière l'adresse :
 *
 * - **compte né de l'invitation** (`invited`) : la personne choisit ici son
 *   nom et son mot de passe ;
 * - **compte déjà existant** : il est rattaché tel quel. Son mot de passe
 *   n'est pas modifiable par ce chemin — le lien prouve qu'on détient la
 *   boîte, pas qu'on est le titulaire d'un compte qui sert peut-être déjà
 *   dans l'administration d'un transporteur.
 *
 * Le jeton est verrouillé pendant la transaction : deux clics simultanés
 * n'activent pas deux fois.
 */
final readonly class ActivateCustomerInvitation
{
    public function __construct(
        private InvitationLookup $lookup,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @param  array{token: string, firstName?: string|null, lastName?: string|null, password?: string|null}  $data
     */
    public function execute(array $data, ?string $ipAddress = null): CustomerUser
    {
        $membership = DB::transaction(function () use ($data): CustomerUser {
            $invitation = $this->lookup->valid($data['token'], forUpdate: true);
            $membership = $invitation->customerUser;
            $user = $membership->user;

            if ($user->status === UserStatus::INVITED) {
                $this->completeNewAccount($user, $data);
            } elseif (! $user->status->allowsLogin()) {
                throw ValidationException::withMessages(['token' => ['Ce compte est désactivé. Contactez votre administrateur.']]);
            }

            $membership->update([
                'status' => UserStatus::ACTIVE,
                'activated_at' => now(),
                'disabled_at' => null,
            ]);

            $invitation->update([
                'status' => CustomerInvitationStatus::ACCEPTED,
                'accepted_at' => now(),
            ]);

            return $membership;
        });

        $this->audit->record($membership->customer, $membership->user, 'user_activated', $membership, null, [
            'email' => $membership->user->email,
        ], $ipAddress);

        return $membership;
    }

    /**
     * @param  array{firstName?: string|null, lastName?: string|null, password?: string|null}  $data
     */
    private function completeNewAccount(User $user, array $data): void
    {
        if (empty($data['password'])) {
            throw ValidationException::withMessages(['password' => ['Choisissez un mot de passe pour activer votre compte.']]);
        }

        $user->forceFill([
            'first_name' => trim((string) ($data['firstName'] ?? '')) ?: $user->first_name,
            'last_name' => trim((string) ($data['lastName'] ?? '')) ?: $user->last_name,
            'password' => $data['password'],
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ])->save();
    }
}
