<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\DTOs\CreateTourData;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Services\TourReferenceResolver;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée une tournée dans l'organisation active.
 *
 * Les six références sont revérifiées ici, et pas seulement dans le Form
 * Request : l'Action doit rester sûre appelée directement, depuis un import ou
 * une commande console.
 */
final readonly class CreateTourAction
{
    public function __construct(
        private TourReferenceResolver $references,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateTourData $data, AuditContext $context): Tour
    {
        $attributes = $data->toAttributes($context->organizationId);

        $this->references->assert($attributes, $context->organizationId);

        return DB::transaction(function () use ($attributes, $context): Tour {
            // Les sept totaux sont posés par défaut en base : sans relecture,
            // le modèle en mémoire les ignore et la réponse les rendrait nuls.
            $tour = Tour::create($attributes)->refresh();

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour.created',
                $tour,
                null,
                $tour->only(['tour_number', 'tour_date', 'agency_id', 'status']),
                null,
                $context->ipAddress,
            );

            return $tour;
        });
    }
}
