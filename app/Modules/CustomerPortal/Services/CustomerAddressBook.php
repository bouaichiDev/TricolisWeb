<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\Addresses\Models\Address;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Les adresses qu'un client peut désigner sur une commande.
 *
 * `OrderScopeGuard::address` ne vérifie que l'organisation : suffisant pour
 * un opérateur du transporteur, qui voit tous ses clients, pas pour le
 * portail. Ici l'adresse doit être rattachée **à ce client ou à l'un de ses
 * sites** — la même règle que l'import de commandes applique déjà.
 *
 * Une commande en cours de modification garde en plus le droit aux adresses
 * qu'elle porte déjà : un destinataire ponctuel, saisi à la création, n'est
 * dans aucun carnet mais reste le sien.
 */
final class CustomerAddressBook
{
    /**
     * @return Builder<Address>
     */
    public function query(Customer $customer): Builder
    {
        $siteIds = DB::table('customer_sites')->where('customer_id', $customer->id)->pluck('id')->all();

        return Address::query()
            ->where('status', 'active')
            ->whereExists(function ($query) use ($customer, $siteIds): void {
                $query->select(DB::raw(1))
                    ->from('entity_addresses')
                    ->whereColumn('entity_addresses.address_id', 'addresses.id')
                    ->where('entity_addresses.organization_id', $customer->organization_id)
                    ->where(function ($scope) use ($customer, $siteIds): void {
                        $scope->where(fn ($owned) => $owned
                            ->where('entity_addresses.entity_type', MorphMap::CUSTOMER)
                            ->where('entity_addresses.entity_id', $customer->id));

                        if ($siteIds !== []) {
                            $scope->orWhere(fn ($site) => $site
                                ->where('entity_addresses.entity_type', MorphMap::CUSTOMER_SITE)
                                ->whereIn('entity_addresses.entity_id', $siteIds));
                        }
                    });
            });
    }

    public function usable(string $addressId, Customer $customer, ?Order $order = null): bool
    {
        if ($this->query($customer)->whereKey($addressId)->exists()) {
            return true;
        }

        return $order !== null
            && OrderService::where('order_id', $order->id)->where('address_id', $addressId)->exists();
    }
}
