<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Services\SequenceReorderer;
use App\Shared\Support\AuditContext;

/**
 * Réordonne les périodes d'une tournée.
 */
final readonly class ReorderTourPeriodsAction
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
        $before = $tour->periods()->orderBy('sequence')->pluck('id')->all();

        $this->reorderer->apply(TourPeriod::class, 'tour_id', $tour->id, 'sequence', $orderedIds);

        $this->audit->execute(
            $context->organizationId,
            $context->user,
            'tour_period.reordered',
            $tour,
            ['periodIds' => $before],
            ['periodIds' => $orderedIds],
            null,
            $context->ipAddress,
        );
    }
}
