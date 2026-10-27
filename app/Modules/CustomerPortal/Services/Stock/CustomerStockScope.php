<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Stock;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Models\StockReservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Le stock du Customer courant — et le point d'entrée unique de toute lecture.
 *
 * Tout part de `StockItem.customer_id = CurrentCustomer.id`, dans son
 * organisation : balances, mouvements et réservations n'existent pour le
 * portail qu'à travers un article du client. Un emplacement du dépôt, lui,
 * n'est jamais listé pour lui-même — seulement quand un article du client y
 * a une balance ; les balances d'autres clients au même emplacement ne sont
 * jamais chargées.
 *
 * Une réservation doit **en plus** porter sur une ligne de commande du même
 * client : les deux chemins doivent concorder.
 */
final readonly class CustomerStockScope
{
    public function __construct(private CurrentCustomerContext $context) {}

    /** @return Builder<StockItem> */
    public function items(): Builder
    {
        return StockItem::query()
            ->where('customer_id', $this->context->customerId())
            ->whereHas('customer', fn (Builder $customer) => $customer->where('organization_id', $this->context->organizationId()));
    }

    /** @return Builder<StockBalance> */
    public function balances(): Builder
    {
        return StockBalance::query()->whereIn('stock_item_id', $this->items()->select('id'));
    }

    /** @return Builder<StockMovement> */
    public function movements(): Builder
    {
        return StockMovement::query()->whereIn('stock_item_id', $this->items()->select('id'));
    }

    /** @return Builder<StockReservation> */
    public function reservations(): Builder
    {
        return StockReservation::query()
            ->whereIn('stock_item_id', $this->items()->select('id'))
            ->whereHas('orderLine.order', fn (Builder $order) => $order
                ->where('customer_id', $this->context->customerId())
                ->where('organization_id', $this->context->organizationId()));
    }

    /** @return Builder<StockAdjustmentRequest> */
    public function adjustmentRequests(): Builder
    {
        return StockAdjustmentRequest::query()
            ->where('organization_id', $this->context->organizationId())
            ->where('customer_id', $this->context->customerId())
            ->whereIn('stock_item_id', $this->items()->select('id'));
    }

    /**
     * Une ressource du périmètre par son identifiant ; 404 sinon — un ULID
     * valide n'est jamais une autorisation.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel
     */
    public function find(Builder $query, string $id): Model
    {
        if (! Str::isUlid($id)) {
            throw (new ModelNotFoundException)->setModel($query->getModel()::class);
        }

        return $query->findOrFail($id);
    }
}
