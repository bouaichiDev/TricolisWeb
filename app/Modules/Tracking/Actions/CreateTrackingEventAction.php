<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tracking\DTOs\CreateTrackingEventData;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Services\TrackingScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un événement de suivi.
 *
 * L'organisation n'est pas fournie par l'appelant : elle est **prise sur la
 * commande**, ce qu'exige le §18. Un événement ne peut donc pas déclarer une
 * organisation différente de celle de la commande qu'il décrit.
 *
 * Si un arrêt est fourni sans tournée, la tournée est déduite de l'arrêt.
 */
final readonly class CreateTrackingEventAction
{
    public function __construct(
        private TrackingScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateTrackingEventData $data, AuditContext $context): TrackingEvent
    {
        $order = $this->guard->order($data->orderId, $context->organizationId);

        if ($data->orderServiceId !== null) {
            $this->guard->orderService($data->orderServiceId, $order);
        }

        $tour = $data->tourId !== null
            ? $this->guard->tour($data->tourId, $context->organizationId)
            : null;

        $tourId = $tour?->id;

        if ($data->tourStopId !== null) {
            $stop = $this->guard->tourStop($data->tourStopId, $tour, $context->organizationId);
            $tourId ??= $stop->tour_id;
        }

        return DB::transaction(function () use ($data, $order, $tourId, $context): TrackingEvent {
            $event = TrackingEvent::create(
                $data->toAttributes($order->organization_id, $tourId, $context->user?->id),
            );

            // Le §25 le precise : l'evenement est deja une donnee metier
            // historique. L'audit ne journalise que l'acte de creation, sans
            // recopier tout le contenu.
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tracking_event.created',
                $event,
                null,
                $event->only(['order_id', 'event_type', 'status', 'occurred_at']),
                null,
                $context->ipAddress,
            );

            return $event;
        });
    }
}
