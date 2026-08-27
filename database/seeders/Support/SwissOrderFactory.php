<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Packages\Models\Package;
use Carbon\CarbonImmutable;

/**
 * Fabrique une commande de démonstration complète.
 *
 * Une commande, c'est ici : son adresse de livraison, son contact, ses articles,
 * ses colis, et **deux services** — le chargement au dépôt, la livraison chez le
 * client. C'est la forme qu'attend la planification : le chargement partage
 * l'adresse du dépôt, ce qui le fait remonter en tête de tournée.
 *
 * **Les totaux sont posés ici**, et non recalculés ensuite : la commande n'est
 * pas créée par `CreateFullOrder`, qui journaliserait neuf cents écritures
 * d'audit pour un jeu d'essai. Les valeurs restent celles que l'Action aurait
 * produites — la somme des colis.
 */
final readonly class SwissOrderFactory
{
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

    public function create(
        string $orderNumber,
        CarbonImmutable $date,
        int $index,
        string $customerId,
    ): Order {
        $address = $this->parts->address($index, $customerId);
        $contact = $this->parts->contact($index, $customerId);

        $weight = SwissOrderParts::PACKAGES * SwissOrderParts::PACKAGE_WEIGHT;
        $volume = SwissOrderParts::PACKAGES * SwissOrderParts::PACKAGE_VOLUME;

        $order = Order::create([
            'organization_id' => $this->organizationId,
            'customer_id' => $customerId,
            'agency_id' => $this->agencyId,
            'depot_id' => $this->depotId,
            'order_number' => $orderNumber,
            'customer_reference' => sprintf('REF-%s-%02d', $date->format('md'), $index + 1),
            'order_type' => 'delivery',
            'order_date' => $date->setTime(8, 0),
            'source' => 'internal',
            'weight' => $weight,
            'volume' => $volume,
            'package_count' => SwissOrderParts::PACKAGES,
            'currency_code' => 'CHF',
            'status' => 'confirmed',
            'created_by' => $this->userId,
        ]);

        $packages = $this->parts->packages($order);
        $this->parts->lines($order);

        // Le chargement d'abord : il se fait au depot, avant de partir.
        $this->service($order, $this->loadingServiceId, $this->depotAddressId, 1, $date, $weight, $volume, $packages);

        $delivery = $this->service($order, $this->deliveryServiceId, $address->id, 2, $date, $weight, $volume, $packages);

        $delivery->contacts()->create([
            'contact_id' => $contact->id,
            'contact_role' => 'delivery',
            'is_primary' => true,
            'first_name_snapshot' => $contact->first_name,
            'last_name_snapshot' => $contact->last_name,
            'phone_snapshot' => $contact->phone,
            'email_snapshot' => $contact->email,
        ]);

        return $order;
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
            'remaining_time_minutes' => 30,
            'weight' => $weight,
            'volume' => $volume,
            'package_count' => count($packages),
            'customer_unit_price' => 0,
            'customer_total_price' => 0,
            // Planifiable : c'est l'etat depuis lequel le pool le propose.
            'status' => 'ready_to_plan',
        ]);

        foreach ($packages as $package) {
            $service->servicePackages()->create([
                'package_id' => $package->id,
                'quantity' => 1,
                'status' => 'pending',
            ]);
        }

        return $service;
    }
}
