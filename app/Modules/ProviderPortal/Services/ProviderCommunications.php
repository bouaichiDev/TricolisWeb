<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Models\OrderCommunication;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Les communications qu'un membre du portail a reçues.
 *
 * Aucun rôle destinataire « fournisseur » n'existe : le seul lien entre une
 * communication et une personne est son **adresse** — le mécanisme de
 * `UserNotifications`. On ne montre donc que les envois partis vers l'adresse
 * du membre connecté, dans l'organisation de son fournisseur, sur un canal
 * externe. Les notifications internes relèvent du back-office ; brouillons,
 * envois en attente, échoués ou annulés ne sont jamais arrivés : absents.
 */
final readonly class ProviderCommunications
{
    public const array DELIVERED = [CommunicationStatus::SENT, CommunicationStatus::DELIVERED, CommunicationStatus::READ];

    public function __construct(private CurrentProviderContext $context) {}

    /**
     * @return Builder<OrderCommunication>
     */
    public function query(): Builder
    {
        return OrderCommunication::query()
            ->where('organization_id', $this->context->organizationId())
            ->whereRaw('LOWER(recipient_email) = ?', [mb_strtolower((string) $this->context->user()->email)])
            ->where('channel', '!=', CommunicationChannel::INTERNAL_NOTIFICATION->value)
            ->whereIn('status', array_map(static fn (CommunicationStatus $status): string => $status->value, self::DELIVERED));
    }

    public function find(string $id): OrderCommunication
    {
        return $this->query()->whereKey($id)->first()
            ?? throw new NotFoundHttpException('Communication introuvable.');
    }
}
