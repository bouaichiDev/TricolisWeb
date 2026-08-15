<?php

declare(strict_types=1);

namespace App\Modules\Tours\Services;

use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Modules\Tours\Models\TourStopService;
use Illuminate\Validation\ValidationException;

/**
 * Contrôles de cohérence d'une affectation (§19).
 *
 * Une affectation relie une période et un service planifié qui appartiennent
 * chacun à une branche différente de l'agrégat. Rien dans le schéma n'empêche
 * de les croiser entre deux tournées : c'est ici que la règle est tenue.
 */
final readonly class AssignmentConsistency
{
    public function __construct(private TourScopeGuard $guard) {}

    /**
     * Le service planifié doit relever d'un arrêt de la tournée de la période.
     */
    public function service(TourPeriod $period, string $tourStopServiceId): TourStopService
    {
        $service = TourStopService::whereKey($tourStopServiceId)
            ->whereHas('tourStop', fn ($stop) => $stop->where('tour_id', $period->tour_id))
            ->first();

        if ($service === null) {
            throw ValidationException::withMessages([
                'tourStopServiceId' => ['Ce service planifié n’appartient pas à la tournée de la période.'],
            ]);
        }

        return $service;
    }

    /**
     * Le colis doit venir de la commande du service concerné.
     */
    public function package(TourStopService $service, ?string $packageId): void
    {
        if ($packageId === null) {
            return;
        }

        $orderService = $service->orderService;

        if ($orderService === null) {
            throw ValidationException::withMessages([
                'packageId' => ['Le service planifié ne référence aucun service de commande.'],
            ]);
        }

        $this->guard->package($packageId, $orderService);
    }

    /**
     * Deux fois la même période, le même service et le même colis n'apporte
     * rien et fausserait les totaux.
     *
     * MySQL considère chaque `NULL` comme distinct : l'index unique ne couvre
     * donc pas le doublon sans colis, qui est refusé ici.
     */
    public function notDuplicated(TourPeriod $period, string $serviceId, ?string $packageId, ?string $ignoreId = null): void
    {
        $exists = TourPeriodAssignment::where('tour_period_id', $period->id)
            ->where('tour_stop_service_id', $serviceId)
            ->when($packageId === null,
                fn ($query) => $query->whereNull('package_id'),
                fn ($query) => $query->where('package_id', $packageId),
            )
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'tourStopServiceId' => ['Cette affectation existe déjà pour cette période.'],
            ]);
        }
    }
}
