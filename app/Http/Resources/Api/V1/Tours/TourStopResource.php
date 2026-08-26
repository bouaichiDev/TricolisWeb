<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

use App\Modules\Tours\Models\TourStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            // Ce que l'arret porte reellement, pour pouvoir le retirer d'un
            // geste. Les affectations historiques en sont exclues : elles
            // racontent ou le service est passe, pas ce qu'il reste a faire.
            'orderServiceIds' => $this->whenLoaded(
                'services',
                fn () => $this->services
                    ->where('is_active_assignment', true)
                    ->pluck('order_service_id')
                    ->values(),
            ),
            // L'adresse en une ligne : la vue en colonnes montre ou le camion
            // s'arrete, pas un identifiant de 26 caracteres.
            'addressLabel' => $this->whenLoaded('address', fn (): ?string => $this->addressLabel()),
            // La carte trace l'ordre des arrêts : sans point, un arrêt existe
            // dans la tournée mais reste absent du tracé.
            'latitude' => $this->whenLoaded('address', fn (): ?float => $this->coordinate($this->address?->latitude)),
            'longitude' => $this->whenLoaded('address', fn (): ?float => $this->coordinate($this->address?->longitude)),
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
