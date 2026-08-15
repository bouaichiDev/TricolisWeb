<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\DTOs\UpdateTourPeriodData;
use App\Modules\Tours\Models\TourPeriod;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modifie une période.
 *
 * Le rattachement à un arrêt reste modifiable, mais uniquement vers un arrêt de
 * la même tournée.
 */
final readonly class UpdateTourPeriodAction
{
    public function __construct(
        private WriteAuditLog $audit,
        private RecalculateTourTotals $totals,
    ) {}

    public function execute(TourPeriod $period, UpdateTourPeriodData $data, AuditContext $context): TourPeriod
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $period;
        }

        if (array_key_exists('tour_stop_id', $attributes) && $attributes['tour_stop_id'] !== null) {
            $belongs = $period->tour?->stops()->whereKey($attributes['tour_stop_id'])->exists() ?? false;

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'tourStopId' => ['Cet arrêt n’appartient pas à la tournée.'],
                ]);
            }
        }

        $updated = DB::transaction(function () use ($period, $attributes, $context): TourPeriod {
            $before = $period->only(array_keys($attributes));
            $period->update($attributes);
            $after = $period->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'tour_period.updated',
                    $period,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $period->fresh();
        });

        if ($period->tour !== null) {
            $this->totals->execute($period->tour);
        }

        return $updated;
    }
}
