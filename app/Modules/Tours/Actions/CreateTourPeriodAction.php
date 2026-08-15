<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\DTOs\CreateTourPeriodData;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée une période dans une tournée.
 *
 * Si un arrêt est fourni, il doit appartenir à **cette** tournée : une période
 * rattachée à l'arrêt d'une autre tournée décrirait un trajet qui n'existe pas.
 */
final readonly class CreateTourPeriodAction
{
    public function __construct(
        private WriteAuditLog $audit,
        private RecalculateTourTotals $totals,
    ) {}

    public function execute(Tour $tour, CreateTourPeriodData $data, AuditContext $context): TourPeriod
    {
        $this->assertStopBelongsToTour($tour, $data->tourStopId);

        $period = DB::transaction(function () use ($tour, $data, $context): TourPeriod {
            $period = TourPeriod::create($data->toAttributes($tour->id));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour_period.created',
                $period,
                null,
                $period->only(['tour_id', 'tour_stop_id', 'period_type', 'sequence', 'status']),
                null,
                $context->ipAddress,
            );

            return $period;
        });

        $this->totals->execute($tour);

        return $period;
    }

    private function assertStopBelongsToTour(Tour $tour, ?string $stopId): void
    {
        if ($stopId === null) {
            return;
        }

        if (! $tour->stops()->whereKey($stopId)->exists()) {
            throw ValidationException::withMessages([
                'tourStopId' => ['Cet arrêt n’appartient pas à la tournée.'],
            ]);
        }
    }
}
