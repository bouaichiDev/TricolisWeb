<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Planning\Jobs\RecalculateTourRouteJob;
use App\Modules\Tours\DTOs\CreateTourStopData;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Services\TourScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un arrêt **et ses services** dans la même transaction.
 *
 * `TourStop "1" *-- "1..*" TourStopService` : un arrêt sans service n'existe
 * pas au modèle. La création est donc atomique — jamais un arrêt vide en base,
 * même transitoirement, même si l'écriture des services échoue.
 *
 * Chaque service planifié doit venir d'une commande de la même organisation que
 * la tournée : planifier le service d'un autre transporteur mettrait une
 * prestation étrangère dans la feuille de route du chauffeur.
 */
final readonly class CreateTourStopAction
{
    public function __construct(
        private TourScopeGuard $guard,
        private WriteAuditLog $audit,
        private RecalculateTourTotals $totals,
    ) {}

    public function execute(Tour $tour, CreateTourStopData $data, AuditContext $context): TourStop
    {
        foreach ($data->services as $index => $service) {
            $this->guard->orderService(
                $service->orderServiceId,
                $tour->organization_id,
                "services.{$index}.orderServiceId",
            );
        }

        $stop = DB::transaction(function () use ($tour, $data, $context): TourStop {
            $stop = TourStop::create($data->toAttributes($tour->id));

            foreach ($data->services as $service) {
                $created = $stop->services()->create($service->toAttributes($stop->id));

                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'tour_stop_service.created',
                    $created,
                    null,
                    $created->only(['tour_stop_id', 'order_service_id', 'sequence_within_stop', 'status']),
                    null,
                    $context->ipAddress,
                );
            }

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour_stop.created',
                $stop,
                null,
                $stop->only(['tour_id', 'address_id', 'sequence', 'status']),
                null,
                $context->ipAddress,
            );

            return $stop;
        });

        $this->totals->execute($tour);
        // La geometrie de la tournee a change : l'itineraire connu decrit
        // un ordre qui n'existe plus. Le calcul part en file, apres la
        // transaction, pour ne pas faire attendre le geste.
        RecalculateTourRouteJob::dispatch($tour->id)->afterCommit();

        return $stop;
    }
}
