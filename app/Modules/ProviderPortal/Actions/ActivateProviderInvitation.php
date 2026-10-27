<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Enums\ProviderInvitationStatus;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Services\ProviderInvitationLookup;
use App\Modules\ProviderPortal\Services\ProviderPortalAudit;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Accepte une invitation au portail fournisseur : le compte devient actif, le
 * rattachement aussi.
 *
 * Un compte né de l'invitation choisit ici son nom et son mot de passe ; un
 * compte existant est rattaché tel quel — le lien prouve qu'on détient la
 * boîte, pas qu'on est le titulaire d'un compte qui sert peut-être déjà
 * ailleurs. Le jeton est verrouillé : deux clics n'activent pas deux fois.
 */
final readonly class ActivateProviderInvitation
{
    public function __construct(
        private ProviderInvitationLookup $lookup,
        private ProviderPortalAudit $audit,
    ) {}

    /**
     * @param  array{token: string, firstName?: string|null, lastName?: string|null, password?: string|null}  $data
     */
    public function execute(array $data, ?string $ipAddress = null): ProviderUser
    {
        $membership = DB::transaction(function () use ($data): ProviderUser {
            $invitation = $this->lookup->valid($data['token'], forUpdate: true);
            $membership = $invitation->providerUser;
            $user = $membership->user;

            if ($user->status === UserStatus::INVITED) {
                $this->completeNewAccount($user, $data);
            } elseif (! $user->status->allowsLogin()) {
                throw ValidationException::withMessages(['token' => ['Ce compte est désactivé. Contactez votre transporteur.']]);
            }

            $membership->update(['status' => UserStatus::ACTIVE, 'activated_at' => now(), 'disabled_at' => null]);
            $invitation->update(['status' => ProviderInvitationStatus::ACCEPTED, 'accepted_at' => now()]);

            return $membership;
        });

        $this->audit->record($membership->provider, $membership->user, 'user_activated', $membership, null, ['email' => $membership->user->email], $ipAddress);

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
