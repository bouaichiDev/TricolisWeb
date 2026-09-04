<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Identity\Models\User;

/**
 * `queue`, `cancel` et `retry` ont leur propre permission : elles déclenchent
 * ou interrompent un envoi vers un tiers, ce qui n'est pas une modification de
 * contenu. Pouvoir corriger un brouillon ne doit pas suffire à l'expédier.
 */
class OrderCommunicationPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'order_communications.view');
    }

    public function view(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'order_communications.create');
    }

    public function update(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.update');
    }

    public function delete(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.delete');
    }

    /**
     * Marquer une notification interne comme lue.
     *
     * **Rien à voir avec `update`.** Celle-ci modifie le contenu d'une
     * communication et demande `order_communications.update` ; celle-là constate
     * qu'on a lu ce qui nous était adressé. Un porteur d'`update` n'a aucune
     * raison de marquer lues les notifications d'un collègue, et le destinataire
     * n'a aucune raison d'avoir `update` pour lire les siennes.
     *
     * Trois conditions, et les trois comptent :
     *
     * - le canal est **interne** : un courriel parti chez un client n'est lu par
     *   personne d'ici, et `read_at` y porte l'accusé de lecture du destinataire
     *   réel — l'écraser falsifierait la trace de l'envoi ;
     * - l'adresse est **la mienne** : c'est ainsi que le destinataire est
     *   désigné, faute de `recipient_user_id` ;
     * - l'organisation est celle du message, vérifiée par l'appartenance.
     */
    public function markAsRead(User $user, OrderCommunication $communication): bool
    {
        if ($communication->channel !== CommunicationChannel::INTERNAL_NOTIFICATION) {
            return false;
        }

        if ($user->email === null || $communication->recipient_email !== $user->email) {
            return false;
        }

        return $this->hasOrganizationAccess($user, $communication->organization_id);
    }

    public function queue(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.queue');
    }

    public function cancel(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.cancel');
    }

    public function retry(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.retry');
    }
}
