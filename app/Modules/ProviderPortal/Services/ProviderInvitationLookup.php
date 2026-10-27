<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Http\Middleware\EnsureProviderPortalContext;
use App\Modules\ProviderPortal\Enums\ProviderInvitationStatus;
use App\Modules\ProviderPortal\Models\ProviderUserInvitation;
use App\Shared\Enums\UserStatus;
use Illuminate\Validation\ValidationException;

/**
 * Retrouve une invitation valable à partir du jeton brut reçu par courriel.
 *
 * Les refus sont distincts — lien inconnu, déjà utilisé, expiré — parce que
 * la personne qui clique n'a pas les mêmes choses à faire. Ils ne révèlent
 * rien qu'elle ne sache déjà : il faut détenir le jeton pour les obtenir.
 */
final class ProviderInvitationLookup
{
    /**
     * @throws ValidationException
     */
    public function valid(string $token, bool $forUpdate = false): ProviderUserInvitation
    {
        $query = ProviderUserInvitation::with(['provider.organization', 'providerUser.user', 'role'])
            ->where('token_hash', ProviderUserInvitation::hashToken($token));

        $invitation = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        if ($invitation === null || $invitation->status === ProviderInvitationStatus::CANCELLED) {
            $this->fail('Ce lien d’invitation n’est pas valable. Demandez-en un nouveau à votre transporteur.');
        }

        if ($invitation->status === ProviderInvitationStatus::ACCEPTED) {
            $this->fail('Cette invitation a déjà été utilisée. Connectez-vous avec votre compte.');
        }

        if ($invitation->status === ProviderInvitationStatus::EXPIRED || $invitation->isExpired()) {
            $invitation->update(['status' => ProviderInvitationStatus::EXPIRED]);
            $this->fail('Cette invitation a expiré. Demandez-en une nouvelle à votre transporteur.');
        }

        if ($invitation->provider->status !== EnsureProviderPortalContext::ACTIVE_PROVIDER
            || $invitation->providerUser === null
            || $invitation->providerUser->status === UserStatus::DISABLED) {
            $this->fail('Ce portail fournisseur n’est plus accessible.');
        }

        return $invitation;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['token' => [$message]]);
    }
}
