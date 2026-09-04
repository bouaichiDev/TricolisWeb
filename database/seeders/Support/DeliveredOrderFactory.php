<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Packages\Models\Package;
use Carbon\CarbonImmutable;

/**
 * Une commande déjà livrée, prête à facturer.
 *
 * Le semis suisse produit des commandes **à planifier** : prix à zéro, statut
 * `ready_to_plan`. Rien n'y est facturable, et une facture à zéro ne se relit
 * pas. Cette fabrique produit l'autre bout du parcours — ce qui a été fait, et
 * qui attend sa facture.
 *
 * **Les prix diffèrent des coûts, et c'est le point.** Ce qu'on facture au
 * client n'est pas ce qu'on reverse au transporteur : c'est la marge, et
 * confondre les deux est l'erreur que la Phase 6 s'emploie à rendre impossible.
 * Les deux valeurs sont donc distinctes dès le semis, faute de quoi un décompte
 * juste et un décompte faux se ressembleraient à l'écran.
 */
final readonly class DeliveredOrderFactory
{
    /** Prix client et coût fournisseur, en CHF, par service. */
    private const float DELIVERY_PRICE = 145.0;

    private const float DELIVERY_COST = 98.0;

    private const float LOADING_PRICE = 55.0;

    private const float LOADING_COST = 34.0;

    private SwissOrderParts $parts;

    public function __construct(
        private string $organizationId,
        private string $agencyId,
        private string $depotId,
        private string $depotAddressId,
        private string $loadingServiceId,
        private string $deliveryServiceId,
        private string $userId,
    ) {
        $this->parts = new SwissOrderParts($organizationId);
    }

    /**
     * @return array{order: Order, loading: OrderService, delivery: OrderService}
     */
    public function create(
        string $orderNumber,
        CarbonImmutable $date,
        int $index,
        SeededCustomer $customer,
    ): array {
        [$weight, $volume] = SwissOrderParts::totals($index);

        $order = Order::create([
            'organization_id' => $this->organizationId,
            'customer_id' => $customer->id,
            'agency_id' => $this->agencyId,
            'depot_id' => $this->depotId,
            'order_number' => $orderNumber,
            'customer_reference' => sprintf('%s-LIV-%s-%02d', $customer->code, $date->format('md'), $index % 100 + 1),
            'order_type' => 'delivery',
            'order_date' => $date->setTime(8, 0),
            'source' => 'internal',
            'weight' => $weight,
            'volume' => $volume,
            'package_count' => SwissOrderParts::packageCount($index),
            'currency_code' => 'CHF',
            // Livrée : la prestation est faite, il reste à la facturer.
            'status' => 'completed',
            'created_by' => $this->userId,
        ]);

        $packages = $this->parts->packages($order, $index);
        $this->parts->lines($order, $index);

        $loading = $this->service(
            $order, $this->loadingServiceId, $this->depotAddressId, 1, $date,
            $weight, $volume, $packages, self::LOADING_PRICE, self::LOADING_COST,
        );

        $delivery = $this->service(
            $order, $this->deliveryServiceId, $customer->addressFor('delivery'), 2, $date,
            $weight, $volume, $packages, self::DELIVERY_PRICE, self::DELIVERY_COST,
        );

        $contact = $customer->contactFor('delivery');

        $delivery->contacts()->create([
            'contact_id' => $contact->id,
            'contact_role' => 'delivery',
            'is_primary' => true,
            'first_name_snapshot' => $contact->first_name,
            'last_name_snapshot' => $contact->last_name,
            'phone_snapshot' => $contact->phone,
            'email_snapshot' => $contact->email,
        ]);

        return ['order' => $order, 'loading' => $loading, 'delivery' => $delivery];
    }

    /**
     * @param  list<Package>  $packages
     */
    private function service(
        Order $order,
        string $serviceId,
        string $addressId,
        int $sequence,
        CarbonImmutable $date,
        float $weight,
        float $volume,
        array $packages,
        float $price,
        float $cost,
    ): OrderService {
        $service = OrderService::create([
            'order_id' => $order->id,
            'service_id' => $serviceId,
            'address_id' => $addressId,
            'service_number' => sprintf('%s-S%d', $order->order_number, $sequence),
            'sequence' => $sequence,
            'requested_date' => $date->toDateString(),
            'requested_from' => $date->setTime(8, 0),
            'requested_to' => $date->setTime(18, 0),
            'quantity' => 1,
            'unit' => 'commande',
            'required_time_minutes' => 30,
            'remaining_time_minutes' => 0,
            'weight' => $weight,
            'volume' => $volume,
            'package_count' => count($packages),
            'customer_unit_price' => $price,
            'customer_total_price' => $price,
            'provider_unit_cost' => $cost,
            'provider_total_cost' => $cost,
            // Fait : c'est le seul etat que le selecteur de facturation retient.
            'status' => 'completed',
        ]);

        foreach ($packages as $package) {
            $service->servicePackages()->create([
                'package_id' => $package->id,
                'quantity' => 1,
                'status' => 'delivered',
            ]);
        }

        return $service;
    }
}
