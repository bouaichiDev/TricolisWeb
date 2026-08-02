<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\DTOs\UpdateOrderCommunicationData;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Exceptions\CommunicationNotEditable;
use App\Modules\Communications\Models\OrderCommunication;
use App\Shared\Support\AuditContext;

/**
 * Modification d'une communication **en brouillon**, et rien d'autre.
 *
 * Le §29 l'impose : « Ne pas permettre l'édition d'une communication déjà
 * envoyée. » Le contenu d'une communication engagée est un snapshot de ce qui
 * part ou est parti ; le modifier réécrirait l'histoire.
 *
 * Poser `scheduledAt` sur un brouillon le fait passer en `SCHEDULED` ; le
 * retirer le ramène en `DRAFT`. Les deux transitions sont autorisées par l'enum.
 */
final readonly class UpdateDraftOrderCommunicationAction
{
    public function __construct(private WriteCommunicationAudit $writer) {}

    public function execute(
        OrderCommunication $communication,
        UpdateOrderCommunicationData $data,
        AuditContext $context,
    ): OrderCommunication {
        if (! $communication->status->allowsContentChanges()) {
            throw CommunicationNotEditable::forEdition($communication->status);
        }

        $attributes = $data->attributes->all();

        if (array_key_exists('scheduled_at', $attributes)) {
            $attributes['status'] = $attributes['scheduled_at'] === null
                ? CommunicationStatus::DRAFT
                : CommunicationStatus::SCHEDULED;
        }

        /** @var OrderCommunication $updated */
        $updated = $this->writer->update($communication, $attributes, 'order_communication.updated', $context);

        return $updated;
    }

    public function delete(OrderCommunication $communication, AuditContext $context): void
    {
        if (! $communication->status->allowsDeletion()) {
            throw CommunicationNotEditable::forDeletion($communication->status);
        }

        $this->writer->deleted(
            $communication,
            'order_communication.deleted',
            ['order_id', 'channel', 'communication_type', 'recipient_role', 'recipient_name', 'status'],
            $context,
        );

        $communication->delete();
    }
}
