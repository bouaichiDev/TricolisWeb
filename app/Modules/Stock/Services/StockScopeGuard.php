<?php

declare(strict_types=1);

namespace App\Modules\Stock\Services;

use App\Modules\Agencies\Models\Depot;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie le périmètre des références de stock.
 *
 * Deux chaînes distinctes cohabitent — le §25 impose de les documenter :
 *
 * ```text
 * StockItem     → customer.organization_id
 * StockLocation → depot.agency.organization_id
 * ```
 *
 * Aucune des deux classes ne porte `organizationId`, et le §2 interdit de
 * l'ajouter. Toute opération croisant les deux vérifie les deux.
 */
final readonly class StockScopeGuard
{
    public function customer(string $customerId, string $organizationId): Customer
    {
        $customer = Customer::where('organization_id', $organizationId)->whereKey($customerId)->first();

        return $customer ?? $this->fail('customerId', 'Ce client n’appartient pas à l’organisation active.');
    }

    /**
     * L'article de catalogue doit relever du même client que l'article de stock.
     */
    public function catalogItem(string $catalogItemId, Customer $customer): CustomerCatalogItem
    {
        $item = CustomerCatalogItem::whereKey($catalogItemId)
            ->whereHas('catalog', fn ($catalog) => $catalog->where('customer_id', $customer->id))
            ->first();

        return $item ?? $this->fail('catalogItemId', 'Cet article de catalogue n’appartient pas à ce client.');
    }

    public function depot(string $depotId, string $organizationId): Depot
    {
        $depot = Depot::whereKey($depotId)
            ->whereHas('agency', fn ($agency) => $agency->where('organization_id', $organizationId))
            ->first();

        return $depot ?? $this->fail('depotId', 'Ce dépôt n’appartient pas à l’organisation active.');
    }

    public function stockItem(string $stockItemId, string $organizationId, string $field = 'stockItemId'): StockItem
    {
        $item = StockItem::inOrganization($organizationId)->whereKey($stockItemId)->first();

        return $item ?? $this->fail($field, 'Cet article de stock n’est pas accessible dans l’organisation active.');
    }

    public function stockLocation(string $locationId, string $organizationId, string $field = 'stockLocationId'): StockLocation
    {
        $location = StockLocation::inOrganization($organizationId)->whereKey($locationId)->first();

        return $location ?? $this->fail($field, 'Cet emplacement n’est pas accessible dans l’organisation active.');
    }

    /**
     * La ligne de commande doit venir d'une commande du client de l'article :
     * on ne réserve pas le stock d'un client pour la commande d'un autre.
     */
    public function orderLine(string $orderLineId, StockItem $stockItem): OrderLine
    {
        $line = OrderLine::whereKey($orderLineId)
            ->whereHas('order', fn ($order) => $order->where('customer_id', $stockItem->customer_id))
            ->first();

        return $line ?? $this->fail('orderLineId', 'Cette ligne de commande n’appartient pas au client de l’article.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
