<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\DTOs\UpdateTourStopData;
use App\Modules\Tours\Models\TourStop;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie un arrêt.
 *
 * `tour_id` n'est pas modifiable : déplacer un arrêt d'une tournée à l'autre
 * romprait la composition et l'unicité `(tour_id, sequence)`.
 */
final readonly class UpdateTourStopAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(TourStop $stop, UpdateTourStopData $data, AuditContext $context): TourStop
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $stop;
        }

        return DB::transaction(function () use ($stop, $attributes, $context): TourStop {
            $before = $stop->only(array_keys($attributes));
            $stop->update($attributes);
            $after = $stop->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'tour_stop.updated',
                    $stop,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $stop->fresh();
        });
    }
}
