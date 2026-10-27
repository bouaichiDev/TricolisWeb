<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderContentEngagement;
use App\Modules\Statuses\Services\StatusMachine;
use App\Shared\Database\MorphMap;

/**
 * Ce qu'un membre du portail peut faire d'une commande — `canEdit`,
 * `canCancel`, `canDuplicate`, `canTrack` — calculé ici, et nulle part
 * ailleurs. Le portail React affiche ces booléens ; il ne teste aucun statut.
 *
 * Chaque capacité croise trois sources, sans liste de statuts propre au
 * client :
 *
 * - la **permission** du membre (`customer.orders.*`, déjà bornée par les
 *   modules ouverts au client) ;
 * - le **workflow** existant (`StatusMachine`) : `allows_content_changes`
 *   pour modifier, transition manuelle vers « annulée » pour annuler ;
 * - l'**état d'exécution** (`OrderContentEngagement`) : une commande
 *   planifiée se traite avec le transporteur, pas seul depuis le portail.
 */
final class CustomerOrderCapabilities
{
    public function __construct(
        private readonly CurrentCustomerContext $context,
        private readonly StatusMachine $statuses,
        private readonly OrderContentEngagement $engagement,
    ) {}

    /**
     * @param  iterable<int, Order>  $orders
     * @return array<string, array{canView: bool, canEdit: bool, canDuplicate: bool, canCancel: bool, canTrack: bool}>
     */
    public function forOrders(iterable $orders): array
    {
        $orders = collect($orders);
        $ids = $orders->pluck('id')->all();
        $planned = $this->engagement->planned($ids);
        $engaged = $this->engagement->engaged($ids);
        $result = [];

        foreach ($orders as $order) {
            $result[$order->id] = $this->evaluate($order, isset($planned[$order->id]), isset($engaged[$order->id]));
        }

        return $result;
    }

    /**
     * @return array{canView: bool, canEdit: bool, canDuplicate: bool, canCancel: bool, canTrack: bool}
     */
    public function for(Order $order): array
    {
        return $this->forOrders([$order])[$order->id];
    }

    /**
     * @return array{canView: bool, canEdit: bool, canDuplicate: bool, canCancel: bool, canTrack: bool}
     */
    private function evaluate(Order $order, bool $planned, bool $engaged): array
    {
        $status = $order->status?->value;

        return [
            'canView' => $this->context->can('customer.orders.view'),
            'canEdit' => $this->context->can('customer.orders.update')
                && $this->statuses->allowsContentChanges(MorphMap::ORDER, $status)
                && ! $engaged,
            'canDuplicate' => $this->context->can('customer.orders.duplicate'),
            'canCancel' => $this->context->can('customer.orders.cancel')
                && $this->statuses->allowsManually(MorphMap::ORDER, $status, OrderStatus::CANCELLED->value)
                && ! $planned,
            // Le suivi (Phase 3) : permission de suivre la commande et accès au
            // module de suivi. Toute commande se suit ; tant que rien n'est
            // planifié, l'écran le dit (`available: false`).
            'canTrack' => $this->context->can('customer.orders.track')
                && $this->context->can('customer.tracking.view'),
        ];
    }
}
