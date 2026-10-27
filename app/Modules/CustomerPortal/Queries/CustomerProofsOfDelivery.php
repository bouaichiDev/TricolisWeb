<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Queries;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les preuves de livraison d'un client — et seulement les **cohérentes**.
 *
 * `ProofOfDelivery` ne porte pas d'organisation : le périmètre passe par la
 * commande, qui doit appartenir au client courant. Et quand la preuve nomme
 * une prestation, celle-ci doit relever de **la même** commande : une preuve
 * dont le service pointerait ailleurs n'est montrée à personne.
 */
final class CustomerProofsOfDelivery
{
    /**
     * @return Builder<ProofOfDelivery>
     */
    public static function query(Customer $customer): Builder
    {
        return ProofOfDelivery::query()
            ->whereIn('order_id', self::orderIds($customer))
            ->where(static fn (Builder $coherent) => $coherent
                ->whereNull('order_service_id')
                ->orWhereExists(static fn ($service) => $service->selectRaw('1')
                    ->from('order_services')
                    ->whereColumn('order_services.id', 'proofs_of_delivery.order_service_id')
                    ->whereColumn('order_services.order_id', 'proofs_of_delivery.order_id')));
    }

    /**
     * Sous-requête des commandes du client, dans son organisation.
     *
     * @return Builder<Order>
     */
    public static function orderIds(Customer $customer): Builder
    {
        return Order::query()->select('id')
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id);
    }
}
