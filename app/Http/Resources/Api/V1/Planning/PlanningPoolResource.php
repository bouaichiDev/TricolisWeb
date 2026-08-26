<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Planning;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une commande vue depuis la planification.
 *
 * Ne sont rendus que les services **encore à planifier** : la relation est
 * chargée filtrée par le contrôleur. Montrer les autres laisserait croire
 * qu'on peut les glisser, alors que le serveur les refuserait.
 *
 * Les totaux portent sur ces seuls services : une commande à moitié planifiée
 * n'apporte à la tournée que ce qui reste.
 *
 * @mixin Order
 */
class PlanningPoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $services = $this->whenLoaded('orderServices', fn () => $this->orderServices, collect());

        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'customerId' => $this->customer_id,
            'customerName' => $this->whenLoaded('customer', fn () => $this->customer->name),
            'status' => $this->status?->value ?? $this->status,
            // La date la plus proche parmi les services restants : c'est elle
            // qui dit ce qui presse, `orders` n'en portant pas.
            'earliestRequestedDate' => $services->min('requested_date')?->toDateString(),
            'serviceCount' => $services->count(),
            'addressCount' => $services->pluck('address_id')->unique()->count(),
            'totalWeight' => (float) $services->sum('weight'),
            'totalVolume' => (float) $services->sum('volume'),
            'totalPackages' => (int) $services->sum('package_count'),
            'services' => $services->map(fn (OrderService $service): array => [
                'id' => $service->id,
                'serviceNumber' => $service->service_number,
                'serviceCode' => $service->relationLoaded('service') ? $service->service?->code : null,
                'serviceName' => $service->relationLoaded('service') ? $service->service?->name : null,
                'status' => $service->status?->value ?? $service->status,
                'addressId' => $service->address_id,
                'addressLabel' => $service->relationLoaded('address') ? $this->label($service) : null,
                // La carte a besoin du point, pas seulement du libellé. Une
                // adresse non géocodée les rend nuls : l'écran la listera sans
                // pouvoir la poser, plutôt que de la placer au large du Ghana.
                'latitude' => $service->relationLoaded('address')
                    ? $this->coordinate($service->address?->latitude)
                    : null,
                'longitude' => $service->relationLoaded('address')
                    ? $this->coordinate($service->address?->longitude)
                    : null,
                'requestedDate' => $service->requested_date?->toDateString(),
                'requestedFrom' => $service->requested_from?->toIso8601String(),
                'requestedTo' => $service->requested_to?->toIso8601String(),
                'weight' => (float) $service->weight,
                'volume' => (float) $service->volume,
                'packageCount' => (int) $service->package_count,
            ])->values(),
        ];
    }

    /** `decimal:8` rend une chaîne : la carte attend un nombre. */
    private function coordinate(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /** Adresse en une ligne, telle qu'un planificateur la lit. */
    private function label(OrderService $service): ?string
    {
        $address = $service->address;

        if ($address === null) {
            return null;
        }

        $parts = array_filter([
            $address->name ?? $address->address_line_1,
            trim(($address->postal_code ?? '').' '.($address->city ?? '')),
        ], static fn (?string $part): bool => $part !== null && trim($part) !== '');

        return implode(' · ', $parts);
    }
}
