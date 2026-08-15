<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Models\OrderCommunication;
use App\Shared\Support\AuditContext;

/**
 * Annule une communication non encore partie.
 *
 * L'enum décide : `DRAFT`, `SCHEDULED` et `QUEUED` peuvent être annulés ; une
 * communication en cours d'envoi ou envoyée ne l'est plus — on ne rattrape pas
 * un message déjà remis au transporteur.
 *
 * Aucune colonne `cancelledAt` n'est ajoutée : le diagramme n'en a pas, et
 * `updatedAt` avec l'audit datent déjà l'annulation.
 */
final readonly class CancelOrderCommunicationAction
{
    public function __construct(private ApplyCommunicationTransition $transition) {}

    public function execute(OrderCommunication $communication, AuditContext $context): OrderCommunication
    {
        return $this->transition->execute(
            $communication,
            CommunicationStatus::CANCELLED,
            [],
            'order_communication.cancelled',
            $context,
        );
    }
}
