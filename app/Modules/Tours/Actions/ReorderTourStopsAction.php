<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Planning\Jobs\RecalculateTourRouteJob;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Services\SequenceReorderer;
use App\Shared\Support\AuditContext;

/**
 * Réordonne les arrêts d'une tournée.
 *
 * L'appelant fournit tous les arrêts dans leur ordre cible ; les séquences sont
 * réécrites de 1 à N, sans trou ni doublon. Une liste partielle est refusée :
 * elle laisserait des arrêts hors numérotation.
 */
final readonly class ReorderTourStopsAction
{
    public function __construct(
        private SequenceReorderer $reorderer,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @param  list<string>  $orderedIds
     */
    public function execute(Tour $tour, array $orderedIds, AuditContext $context): void
    {
        $before = $tour->stops()->orderBy('sequence')->pluck('id')->all();

        $this->reorderer->apply(TourStop::class, 'tour_id', $tour->id, 'sequence', $orderedIds);

        $this->audit->execute(
            $context->organizationId,
            $context->user,
            'tour_stop.reordered',
            $tour,
            ['stopIds' => $before],
            ['stopIds' => $orderedIds],
            null,
            $context->ipAddress,
        );

        // La geometrie de la tournee a change : l'itineraire connu decrit
        // un ordre qui n'existe plus. Le calcul part en file, apres la
        // transaction, pour ne pas faire attendre le geste.
        RecalculateTourRouteJob::dispatch($tour->id)->afterCommit();
    }
}
