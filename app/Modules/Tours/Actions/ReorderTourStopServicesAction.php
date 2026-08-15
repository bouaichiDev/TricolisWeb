<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use App\Modules\Tours\Services\SequenceReorderer;
use App\Shared\Support\AuditContext;

/**
 * Réordonne les services d'un arrêt.
 *
 * Les services désactivés sont inclus : ils occupent une séquence dans l'index
 * unique `(tour_stop_id, sequence_within_stop)`, et les exclure laisserait des
 * lignes dans le décalage temporaire de la réattribution.
 */
final readonly class ReorderTourStopServicesAction
{
    public function __construct(
        private SequenceReorderer $reorderer,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @param  list<string>  $orderedIds
     */
    public function execute(TourStop $stop, array $orderedIds, AuditContext $context): void
    {
        $before = $stop->services()->orderBy('sequence_within_stop')->pluck('id')->all();

        $this->reorderer->apply(TourStopService::class, 'tour_stop_id', $stop->id, 'sequence_within_stop', $orderedIds);

        $this->audit->execute(
            $context->organizationId,
            $context->user,
            'tour_stop_service.reordered',
            $stop,
            ['serviceIds' => $before],
            ['serviceIds' => $orderedIds],
            null,
            $context->ipAddress,
        );
    }
}
