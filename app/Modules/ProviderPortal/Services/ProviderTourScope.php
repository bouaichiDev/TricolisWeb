<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Les tournées attribuées au fournisseur courant.
 *
 * Une tournée n'appartient au fournisseur que si elle porte son identifiant
 * **dans son organisation**, et ne lui est montrée qu'une fois sortie du
 * brouillon : le planificateur la compose encore. Tout le reste — autre
 * fournisseur, autre organisation, brouillon, inexistante — répond le même 404,
 * et une tournée réattribuée disparaît dès la requête suivante.
 */
final readonly class ProviderTourScope
{
    /** Statuts où le fournisseur peut encore changer chauffeur et véhicule. */
    public const array ASSIGNABLE = [TourStatus::PLANNED, TourStatus::CONFIRMED];

    /** Les onglets « À effectuer / En cours / Terminées » de l'exécution. */
    public const array STATES = [
        'todo' => [TourStatus::PLANNED, TourStatus::CONFIRMED],
        'running' => [TourStatus::IN_PROGRESS],
        'done' => [TourStatus::COMPLETED, TourStatus::CANCELLED],
    ];

    public function __construct(private CurrentProviderContext $context) {}

    /**
     * @return list<string>
     */
    public static function visibleStatuses(): array
    {
        return array_values(array_map(
            static fn (TourStatus $status): string => $status->value,
            array_filter(TourStatus::cases(), static fn (TourStatus $status): bool => $status !== TourStatus::DRAFT),
        ));
    }

    /**
     * @return Builder<Tour>
     */
    public function tours(): Builder
    {
        return Tour::where('organization_id', $this->context->organizationId())
            ->where('provider_id', $this->context->providerId())
            ->whereIn('status', self::visibleStatuses());
    }

    public function tour(string $id, bool $lock = false): Tour
    {
        $query = $this->tours()->whereKey($id);

        return ($lock ? $query->lockForUpdate() : $query)->first()
            ?? throw new NotFoundHttpException('Tournée introuvable.');
    }
}
