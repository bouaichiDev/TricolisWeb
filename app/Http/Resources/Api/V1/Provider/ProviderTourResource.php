<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une tournée vue du portail fournisseur : ce qu'il faut pour l'organiser et
 * l'exécuter, rien de commercial. Les arrêts ne sont rendus que s'ils ont été
 * chargés (détail).
 *
 * @mixin Tour
 */
class ProviderTourResource extends JsonResource
{
    /** Statuts où le fournisseur peut encore changer chauffeur et véhicule. */
    public const array ASSIGNABLE = [TourStatus::PLANNED, TourStatus::CONFIRMED];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof TourStatus ? $this->status : TourStatus::from((string) $this->status);
        $canAssign = app(CurrentProviderContext::class)->can('provider.tours.assign-resources')
            && in_array($status, self::ASSIGNABLE, true)
            && $this->locked_by === null;

        return [
            'id' => $this->id,
            'tourNumber' => $this->tour_number,
            'tourDate' => $this->tour_date?->toDateString(),
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'agency' => $this->whenLoaded('agency', fn () => $this->agency?->name),
            'depot' => $this->whenLoaded('depot', fn () => $this->depot?->name),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver === null ? null : ['id' => $this->driver->id, 'code' => $this->driver->code, 'name' => $this->driver->name]),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle === null ? null : ['id' => $this->vehicle->id, 'code' => $this->vehicle->code, 'registrationNumber' => $this->vehicle->registration_number]),
            'plannedStartAt' => $this->planned_start_at?->toIso8601String(),
            'plannedEndAt' => $this->planned_end_at?->toIso8601String(),
            'actualStartAt' => $this->actual_start_at?->toIso8601String(),
            'actualEndAt' => $this->actual_end_at?->toIso8601String(),
            'instructions' => $this->instructions,
            'totals' => [
                'weight' => (string) $this->total_weight,
                'volume' => (string) $this->total_volume,
                'packages' => $this->total_packages,
                'distanceMeters' => $this->distance_meters,
                'drivingMinutes' => $this->driving_time_minutes,
                'workingMinutes' => $this->working_time_minutes,
            ],
            'stops' => ProviderTourStopResource::collection($this->whenLoaded('stops')),
            'actions' => ['canAssign' => $canAssign],
        ];
    }
}
