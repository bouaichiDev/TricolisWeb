<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Les chauffeurs et véhicules du fournisseur courant, et eux seuls.
 *
 * Toute lecture filtre le fournisseur **et** l'organisation du contexte ;
 * toute écriture relit sous verrou. Une ressource d'un autre fournisseur, du
 * transporteur lui-même (`provider_id` nul) ou inexistante répond le même
 * 404 : le portail n'apprend pas qu'elle existe.
 */
final readonly class ProviderResourceScope
{
    /** Une ressource affectée à une tournée dans ces statuts ne se désactive pas. */
    public const array ENGAGING = [TourStatus::PLANNED, TourStatus::CONFIRMED, TourStatus::IN_PROGRESS];

    public function __construct(private CurrentProviderContext $context) {}

    /**
     * @return Builder<Driver>
     */
    public function drivers(): Builder
    {
        return Driver::where('organization_id', $this->context->organizationId())->where('provider_id', $this->context->providerId());
    }

    /**
     * @return Builder<Vehicle>
     */
    public function vehicles(): Builder
    {
        return Vehicle::where('organization_id', $this->context->organizationId())->where('provider_id', $this->context->providerId());
    }

    public function driver(string $id, bool $lock = false): Driver
    {
        /** @var Driver */
        return $this->find($this->drivers(), $id, $lock);
    }

    public function vehicle(string $id, bool $lock = false): Vehicle
    {
        /** @var Vehicle */
        return $this->find($this->vehicles(), $id, $lock);
    }

    /**
     * Le chauffeur ou le véhicule est-il affecté à une tournée à venir ou en cours ?
     */
    public function isEngaged(Driver|Vehicle $resource): bool
    {
        $column = $resource instanceof Driver ? 'driver_id' : 'vehicle_id';

        return Tour::where('organization_id', $resource->organization_id)
            ->where($column, $resource->id)
            ->whereIn('status', array_map(static fn (TourStatus $status): string => $status->value, self::ENGAGING))
            // Verrou : une affectation concurrente attend la fin de la désactivation.
            ->lockForUpdate()
            ->exists();
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function find(Builder $query, string $id, bool $lock): Model
    {
        $model = ($lock ? $query->lockForUpdate() : $query)->whereKey($id)->first();

        return $model ?? throw new NotFoundHttpException('Ressource introuvable.');
    }
}
