<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Orders\Models\Order;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\TrackingEventDefinition;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Collection;

/**
 * Les données déjà enregistrées dont la timeline du client se nourrit.
 *
 * Trois lectures, toutes bornées à la commande :
 *
 * - les changements de statut de la commande, lus dans l'audit — les valeurs
 *   brutes n'en sortent pas, seul le code d'arrivée ;
 * - les événements du **parcours client** dont l'étape est marquée
 *   `visible_to_customer` par le transporteur — un chargement au dépôt ou un
 *   événement saisi à la main sans étape restent internes ;
 * - les preuves de livraison.
 */
final readonly class TimelineSources
{
    /**
     * @return Collection<int, array{status: string|null, at: string|null}>
     */
    public function statusChanges(Order $order): Collection
    {
        return AuditLog::query()
            ->where('organization_id', $order->organization_id)
            ->where('entity_type', MorphMap::ORDER)
            ->where('entity_id', $order->id)
            ->where('action', 'status_changed')
            ->oldest('created_at')
            ->get(['new_values', 'created_at'])
            ->map(static fn (AuditLog $log): array => [
                'status' => is_string($log->new_values['status'] ?? null) ? $log->new_values['status'] : null,
                'at' => $log->created_at?->toIso8601String(),
            ]);
    }

    /**
     * Étapes du parcours que le transporteur montre au client, par code.
     *
     * @return Collection<string, TrackingEventDefinition>
     */
    public function journeyDefinitions(Order $order): Collection
    {
        return TrackingEventDefinition::query()
            ->where('organization_id', $order->organization_id)
            ->where('visible_to_customer', true)
            ->orderBy('position')
            ->get(['code', 'title', 'description', 'service_id', 'active', 'position'])
            ->keyBy('code');
    }

    /**
     * Événements du parcours visibles par le client, avec le titre de leur étape.
     *
     * @param  Collection<string, TrackingEventDefinition>  $definitions
     * @return Collection<int, array{code: string, title: string, description: string|null, at: string|null, orderServiceId: string|null}>
     */
    public function journeyEvents(Order $order, Collection $definitions): Collection
    {
        if ($definitions->isEmpty()) {
            return collect();
        }

        return TrackingEvent::query()
            ->where('organization_id', $order->organization_id)
            ->where('order_id', $order->id)
            ->whereIn('event_type', $definitions->keys())
            ->oldest('occurred_at')
            ->get(['event_type', 'description', 'occurred_at', 'order_service_id'])
            ->map(static function (TrackingEvent $event) use ($definitions): array {
                $definition = $definitions->get($event->event_type);

                return [
                    'code' => $event->event_type,
                    'title' => $definition->title,
                    'description' => $event->description ?? $definition->description,
                    'at' => $event->occurred_at?->toIso8601String(),
                    'orderServiceId' => $event->order_service_id,
                ];
            });
    }

    /**
     * @return Collection<int, ProofOfDelivery>
     */
    public function proofs(Order $order): Collection
    {
        return ProofOfDelivery::query()
            ->where('order_id', $order->id)
            ->oldest('delivered_at')
            ->get(['id', 'order_service_id', 'recipient_name', 'delivered_at']);
    }
}
