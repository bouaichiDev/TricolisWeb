<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Http\Resources\Api\V1\Provider\ProviderTourResource;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\ProviderPortal\Services\ProviderResourceScope;
use App\Modules\ProviderPortal\Services\ProviderTourScope;
use App\Modules\Tours\Actions\UpdateTourAction;
use App\Modules\Tours\DTOs\UpdateTourData;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Affecte (ou retire) le chauffeur et le véhicule d'une tournée attribuée au
 * fournisseur. Le planificateur garde la structure de la tournée ; le
 * fournisseur ne choisit que qui roule, et avec quoi.
 *
 * Tout est vérifié sous verrou — ressources d'abord, tournée ensuite, dans le
 * même ordre que la désactivation d'une ressource — puis confié à
 * `UpdateTourAction`, qui audite. Deux affectations simultanées du même
 * chauffeur ne passent pas toutes les deux.
 *
 * @phpstan-type Input array{driverId?: string|null, vehicleId?: string|null}
 */
final readonly class AssignProviderTourResources
{
    public function __construct(
        private ProviderTourScope $tours,
        private ProviderResourceScope $resources,
        private UpdateTourAction $update,
    ) {}

    /**
     * @param  Input  $input
     */
    public function execute(string $tourId, array $input, AuditContext $context): Tour
    {
        return DB::transaction(function () use ($tourId, $input, $context): Tour {
            $driver = $this->resource('driverId', $input, fn (string $id) => $this->resources->driver($id, lock: true));
            $vehicle = $this->resource('vehicleId', $input, fn (string $id) => $this->resources->vehicle($id, lock: true));
            $tour = $this->tours->tour($tourId, lock: true);

            if (! in_array($tour->status, ProviderTourResource::ASSIGNABLE, true) || $tour->locked_by !== null) {
                $this->fail('tour', $tour->locked_by !== null
                    ? 'Cette tournée est en cours de planification : réessayez plus tard.'
                    : 'Cette tournée ne peut plus changer de chauffeur ni de véhicule.');
            }

            if ($driver !== null && $this->overlaps($tour, 'driver_id', $driver->id)) {
                $this->fail('driverId', 'Ce chauffeur est déjà affecté à une tournée sur ce créneau.');
            }

            if ($vehicle !== null) {
                if ($this->overlaps($tour, 'vehicle_id', $vehicle->id)) {
                    $this->fail('vehicleId', 'Ce véhicule est déjà affecté à une tournée sur ce créneau.');
                }

                if ((float) $vehicle->payload_capacity < (float) $tour->total_weight || (float) $vehicle->volume_capacity < (float) $tour->total_volume) {
                    $this->fail('vehicleId', 'La capacité de ce véhicule est insuffisante pour cette tournée.');
                }
            }

            $attributes = [];
            foreach (['driverId' => $driver, 'vehicleId' => $vehicle] as $key => $model) {
                if (array_key_exists($key, $input)) {
                    $attributes[$key] = $model?->id;
                }
            }

            return $this->update->execute($tour, UpdateTourData::fromValidated($attributes), $context);
        });
    }

    /**
     * La ressource demandée, de ce fournisseur et active ; `null` pour la retirer.
     *
     * @param  Input  $input
     * @param  callable(string): (Driver|Vehicle)  $find
     */
    private function resource(string $field, array $input, callable $find): Driver|Vehicle|null
    {
        if (! array_key_exists($field, $input) || $input[$field] === null) {
            return null;
        }

        try {
            $model = $find($input[$field]);
        } catch (NotFoundHttpException) {
            $this->fail($field, 'Cette ressource ne fait pas partie de votre flotte.');
        }

        if ($model->status !== 'active') {
            $this->fail($field, 'Cette ressource est inactive.');
        }

        return $model;
    }

    /**
     * Une autre tournée du même jour, ni annulée ni terminée, utilise-t-elle
     * déjà la ressource sur un créneau qui recouvre celui-ci ? Sans horaires
     * prévus, la journée entière compte.
     */
    private function overlaps(Tour $tour, string $column, string $resourceId): bool
    {
        return Tour::where('organization_id', $tour->organization_id)
            ->where($column, $resourceId)
            ->whereKeyNot($tour->id)
            ->whereDate('tour_date', $tour->tour_date)
            ->whereNotIn('status', [TourStatus::CANCELLED->value, TourStatus::COMPLETED->value])
            ->when($tour->planned_start_at !== null && $tour->planned_end_at !== null, static fn ($query) => $query->where(static fn ($slot) => $slot
                ->whereNull('planned_start_at')
                ->orWhereNull('planned_end_at')
                ->orWhere(static fn ($both) => $both->where('planned_start_at', '<', $tour->planned_end_at)->where('planned_end_at', '>', $tour->planned_start_at))))
            ->lockForUpdate()
            ->exists();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
