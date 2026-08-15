<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\DTOs\UpdateTourData;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Services\TourReferenceResolver;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie une tournée.
 *
 * Un changement de statut est audité séparément (`tour.status_changed`) : il
 * décrit un événement d'exploitation, pas une correction de saisie, et se
 * cherche autrement dans le journal.
 */
final readonly class UpdateTourAction
{
    public function __construct(
        private TourReferenceResolver $references,
        private WriteAuditLog $audit,
    ) {}

    public function execute(Tour $tour, UpdateTourData $data, AuditContext $context): Tour
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $tour;
        }

        $this->references->assert($attributes, $context->organizationId, $tour);

        return DB::transaction(function () use ($tour, $attributes, $context): Tour {
            $before = $tour->only(array_keys($attributes));
            $tour->update($attributes);
            $after = $tour->fresh()->only(array_keys($attributes));

            if ($before === $after) {
                return $tour->fresh();
            }

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour.updated',
                $tour,
                $before,
                $after,
                null,
                $context->ipAddress,
            );

            if (($before['status'] ?? null) !== ($after['status'] ?? null) && array_key_exists('status', $after)) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'tour.status_changed',
                    $tour,
                    ['status' => $before['status'] ?? null],
                    ['status' => $after['status']],
                    null,
                    $context->ipAddress,
                );
            }

            return $tour->fresh();
        });
    }
}
