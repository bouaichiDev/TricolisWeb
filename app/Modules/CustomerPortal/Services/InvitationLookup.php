<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\CustomerPortal\Enums\CustomerInvitationStatus;
use App\Modules\CustomerPortal\Models\CustomerUserInvitation;
use App\Modules\Customers\Enums\CustomerStatus;
use Illuminate\Validation\ValidationException;

/**
 * Retrouve une invitation valable à partir du jeton brut reçu par courriel.
 *
 * Les refus sont distincts — lien inconnu, déjà utilisé, expiré — parce que
 * la personne qui clique n'a pas les mêmes choses à faire : se connecter,
 * ou demander un nouveau lien. Ils ne révèlent rien qu'elle ne sache déjà :
 * il faut détenir le jeton pour les obtenir.
 *
 * Une invitation dépassée est marquée `expired` au passage : son statut
 * reflète alors ce que le lien vaut réellement.
 */
final class InvitationLookup
{
    /**
     * @throws ValidationException
     */
    public function valid(string $token, bool $forUpdate = false): CustomerUserInvitation
    {
        $query = CustomerUserInvitation::with(['customer.organization', 'customerUser.user', 'role'])
            ->where('token_hash', CustomerUserInvitation::hashToken($token));

        $invitation = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        if ($invitation === null || $invitation->status === CustomerInvitationStatus::CANCELLED) {
            $this->fail('Ce lien d’invitation n’est pas valable. Demandez-en un nouveau à votre administrateur.');
        }

        if ($invitation->status === CustomerInvitationStatus::ACCEPTED) {
            $this->fail('Cette invitation a déjà été utilisée. Connectez-vous avec votre compte.');
        }

        if ($invitation->status === CustomerInvitationStatus::EXPIRED || $invitation->isExpired()) {
            $invitation->update(['status' => CustomerInvitationStatus::EXPIRED]);
            $this->fail('Cette invitation a expiré. Demandez-en une nouvelle à votre administrateur.');
        }

        if ($invitation->customer->status !== CustomerStatus::ACTIVE || $invitation->customerUser === null) {
            $this->fail('Ce portail client n’est plus accessible.');
        }

        return $invitation;
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['token' => [$message]]);
    }
}
