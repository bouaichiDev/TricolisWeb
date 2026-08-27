<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\DTOs\CreateOrderData;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Planning\Actions\GeocodeMissingAddresses;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderScopeGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Crée une commande complète — en-tête, lignes, colis, affectations et services
 * — en une seule transaction.
 *
 * Toute erreur, y compris de validation d'une sous-ressource, annule l'ensemble :
 * aucune commande partielle ne subsiste. Un seul audit est écrit pour la
 * création, plutôt qu'un par affectation technique.
 */
final readonly class CreateFullOrder
{
    public function __construct(
        private OrderScopeGuard $guard,
        private GenerateOrderNumber $numbers,
        private CreateOrderLines $lines,
        private CreateOrderPackages $packages,
        private CreateOrderServices $services,
        private RecalculateOrderTotals $totals,
        private GeocodeMissingAddresses $geocode,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateOrderData $data, string $organizationId, User $user, ?Request $request = null): Order
    {
        return DB::transaction(function () use ($data, $organizationId, $user, $request): Order {
            $customer = $this->guard->customer($data->customerId, $organizationId);
            $this->guard->agency($data->agencyId, $organizationId);

            if ($data->depotId !== null) {
                $this->guard->depot($data->depotId, $organizationId);
            }

            $order = Order::create($this->headerAttributes($data, $organizationId, $user));

            $lines = $this->lines->execute($order, $customer, $data->lines);
            $packages = $this->packages->execute($order, $data->packages, $lines);
            $services = $this->services->execute($order, $data->services, $packages);

            $this->totals->execute($order);

            // Une commande dont l'adresse n'est pas situee ne peut ni se poser
            // sur la carte ni entrer dans un calcul d'itineraire. Le geocodage
            // part en file, apres validation de la transaction.
            $this->geocode->execute(
                collect($services)->pluck('address_id')->all(),
                $organizationId,
            );

            $this->audit->execute(
                $organizationId,
                $user,
                'created',
                $order,
                null,
                [
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'line_count' => count($data->lines),
                    'package_count' => count($data->packages),
                    'service_count' => count($data->services),
                ],
                $request,
            );

            return $order;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function headerAttributes(CreateOrderData $data, string $organizationId, User $user): array
    {
        $attributes = $data->attributes;
        $orderDate = $attributes['order_date'] ?? now();

        return array_merge([
            'source' => OrderSource::INTERNAL->value,
            'status' => OrderStatus::DRAFT->value,
            'currency_code' => 'MAD',
        ], $attributes, [
            'organization_id' => $organizationId,
            'customer_id' => $data->customerId,
            'agency_id' => $data->agencyId,
            'depot_id' => $data->depotId,
            'order_date' => $orderDate,
            // Le numéro fourni par l'appelant est ignoré : il est attribué par
            // la séquence, sous verrou, pour garantir l'unicité.
            'order_number' => $this->numbers->execute($organizationId, (int) date('Y', strtotime((string) $orderDate))),
            'created_by' => $user->id,
        ]);
    }
}
