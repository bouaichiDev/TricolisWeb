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
 * Une commande, c'est ici : le client et son adresse de livraison, ses articles,
 * ses colis, et **de une à quatre prestations** — {@see SwissServiceMix} décide
 * lesquelles. Le chargement, quand la commande en porte un, se fait à l'adresse
 * du dépôt : c'est ce qui le fait remonter en tête de tournée.
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
        private SwissServiceMix $mix,
        private string $userId,
    ) {
        $this->parts = new SwissOrderParts($organizationId);
    }

    public function create(
        string $orderNumber,
        CarbonImmutable $date,
        int $index,
        SeededCustomer $customer,
    ): Order {
        [$weight, $volume] = SwissOrderParts::totals($index);

        $order = Order::create([
            'organization_id' => $this->organizationId,
            'customer_id' => $customer->id,
            'agency_id' => $this->agencyId,
            'depot_id' => $this->depotId,
            'order_number' => $orderNumber,
            // Le code client préfixe la référence : c'est par elle qu'on
            // retrouve une commande quand le client la réclame, et son propre
            // code est le premier repère qu'il donne.
            'customer_reference' => sprintf('%s-REF-%s-%02d', $customer->code, $date->format('md'), $index % 100 + 1),
            'order_type' => 'delivery',
            'order_date' => $date->setTime(8, 0),
            'source' => 'internal',
            'weight' => $weight,
            'volume' => $volume,
            'package_count' => SwissOrderParts::packageCount($index),
            'currency_code' => 'CHF',
            'status' => 'confirmed',
            'created_by' => $this->userId,
        ]);

        $packages = $this->parts->packages($order, $index);
        $this->parts->lines($order, $index);

        foreach ($this->mix->for($index, $customer) as $sequence => $specification) {
            $service = $this->service($order, $specification, $sequence + 1, $date, $weight, $volume, $packages);

            if ($specification['contactRole'] === null) {
                continue;
            }

            $this->attachContact($service, $customer, $specification['contactRole']);
        }

        return $order;
    }

    /**
     * @param  array{code: string, serviceId: string, addressId: string, minutes: int, contactRole: string|null}  $specification
     * @param  list<Package>  $packages
     */
    private function service(
        Order $order,
        array $specification,
        int $sequence,
        CarbonImmutable $date,
        float $weight,
        float $volume,
        array $packages,
    ): OrderService {
        $service = OrderService::create([
            'order_id' => $order->id,
            'service_id' => $specification['serviceId'],
            'address_id' => $specification['addressId'],
            'service_number' => sprintf('%s-S%d', $order->order_number, $sequence),
            'sequence' => $sequence,
            'requested_date' => $date->toDateString(),
            'requested_from' => $date->setTime(8, 0),
            'requested_to' => $date->setTime(18, 0),
            'quantity' => 1,
            'unit' => 'commande',
            'required_time_minutes' => $specification['minutes'],
            'remaining_time_minutes' => $specification['minutes'],
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

    /**
     * Le contact prévenu pour cette prestation, figé au moment de la commande.
     *
     * Les instantanés ne sont pas une redondance : le contact du carnet peut
     * changer de numéro l'an prochain, la commande doit garder celui qu'on avait
     * appelé.
     */
    private function attachContact(OrderService $service, SeededCustomer $customer, string $role): void
    {
        $contact = $customer->contactFor($role);

        $service->contacts()->create([
            'contact_id' => $contact->id,
            'contact_role' => $role,
            'is_primary' => true,
            'first_name_snapshot' => $contact->first_name,
            'last_name_snapshot' => $contact->last_name,
            'phone_snapshot' => $contact->phone,
            'email_snapshot' => $contact->email,
        ]);
    }
}
