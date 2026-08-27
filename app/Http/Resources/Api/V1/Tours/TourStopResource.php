<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Arrêt vu depuis une liste.
 *
 * @mixin TourStop
 */
class TourStopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tourId' => $this->tour_id,
            'addressId' => $this->address_id,
            'sequence' => $this->sequence,
            'groupingKey' => $this->grouping_key,
            'generationMode' => $this->generation_mode,
            'plannedArrivalAt' => $this->planned_arrival_at?->toIso8601String(),
            'plannedDepartureAt' => $this->planned_departure_at?->toIso8601String(),
            'actualArrivalAt' => $this->actual_arrival_at?->toIso8601String(),
            'actualDepartureAt' => $this->actual_departure_at?->toIso8601String(),
            'waitingMinutes' => $this->waiting_minutes,
            'serviceMinutes' => $this->service_minutes,
            'status' => $this->status->value,
            'serviceCount' => $this->whenCounted('services'),
            // Le temps total sur place, somme des services actifs de l'arret.
            'totalServiceMinutes' => $this->whenLoaded(
                'services',
                fn (): int => (int) $this->services
                    ->where('is_active_assignment', true)
                    ->sum(fn ($assignment): int => (int) ($assignment->orderService?->required_time_minutes ?? 0)),
            ),
            // Ce que l'arret porte reellement, pour pouvoir le retirer d'un
            // geste et remonter a la commande. Les affectations historiques en
            // sont exclues : elles racontent ou le service est passe, pas ce
            // qu'il reste a faire.
            'orderServiceIds' => $this->whenLoaded(
                'services',
                fn () => $this->services
                    ->where('is_active_assignment', true)
                    ->pluck('order_service_id')
                    ->values(),
            ),
            'orders' => $this->whenLoaded('services', fn () => $this->plannedOrders()),
            // L'adresse en une ligne : la vue en colonnes montre ou le camion
            // s'arrete, pas un identifiant de 26 caracteres.
            'addressLabel' => $this->whenLoaded('address', fn (): ?string => $this->addressLabel()),
            // La carte trace l'ordre des arrêts : sans point, un arrêt existe
            // dans la tournée mais reste absent du tracé.
            'latitude' => $this->whenLoaded('address', fn (): ?float => $this->coordinate($this->address?->latitude)),
            'longitude' => $this->whenLoaded('address', fn (): ?float => $this->coordinate($this->address?->longitude)),
        ];
    }

    /**
     * Ce que l'arrêt porte, commande par commande.
     *
     * Un arrêt regroupe les services d'une même adresse : deux services d'une
     * même commande n'y font qu'une ligne, et c'est cette ligne qu'on déplie
     * pour savoir ce que le camion vient déposer — chez qui, combien de colis,
     * pour quel poids, et pour combien de temps sur place.
     *
     * Les grandeurs sont celles des **services posés ici**, pas de la commande
     * entière : une commande à moitié planifiée ailleurs n'apporte à cet arrêt
     * que ce qu'il en reçoit.
     *
     * @return list<array<string, mixed>>
     */
    private function plannedOrders(): array
    {
        return $this->services
            ->where('is_active_assignment', true)
            ->map(fn ($assignment) => $assignment->orderService)
            ->filter(fn ($service): bool => $service?->order !== null)
            ->groupBy(fn ($service): string => $service->order_id)
            ->map(fn ($services): array => $this->orderLine($services))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OrderService>  $services
     * @return array<string, mixed>
     */
    private function orderLine($services): array
    {
        $order = $services->first()->order;

        return [
            'id' => $order->id,
            'orderNumber' => $order->order_number,
            'customerReference' => $order->customer_reference,
            // Le destinataire : le client de la commande, pas l'organisation.
            'customerId' => $order->customer_id,
            'customerName' => $order->relationLoaded('customer') ? $order->customer?->name : null,
            'weight' => (float) $services->sum('weight'),
            'volume' => (float) $services->sum('volume'),
            'packageCount' => (int) $services->sum('package_count'),
            // Le temps que le camion passe ici pour cette commande.
            'serviceMinutes' => (int) $services->sum('required_time_minutes'),
            'services' => $services->map(fn ($service): array => [
                'id' => $service->id,
                'serviceNumber' => $service->service_number,
                'name' => $service->relationLoaded('service') ? $service->service?->name : null,
                'code' => $service->relationLoaded('service') ? $service->service?->code : null,
                'minutes' => (int) $service->required_time_minutes,
                'status' => $service->status?->value ?? $service->status,
            ])->values()->all(),
        ];
    }

    /** `decimal:8` rend une chaîne : la carte attend un nombre. */
    private function coordinate(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /** Adresse en une ligne, telle qu'un planificateur la lit. */
    private function addressLabel(): ?string
    {
        $address = $this->address;

        if ($address === null) {
            return null;
        }

        $parts = array_filter([
            $address->name ?? $address->address_line_1,
            trim(($address->postal_code ?? '').' '.($address->city ?? '')),
        ], static fn (?string $part): bool => $part !== null && trim($part) !== '');

        return implode(' · ', $parts) ?: null;
    }
}
