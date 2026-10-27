<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les chiffres du tableau de bord fournisseur, lus en base — aucun n'est
 * simulé ni déduit.
 *
 * Chaque requête filtre le fournisseur **et** l'organisation du contexte : une
 * tournée qui porterait l'identifiant du fournisseur dans une autre
 * organisation n'est pas la sienne. « Aujourd'hui » est le jour de
 * l'organisation, pas celui du serveur.
 */
final readonly class ProviderDashboardComposer
{
    public const string ACTIVE = 'active';

    /**
     * @return array{toursToday: int, toursInProgress: int, toursCompletedToday: int, activeDrivers: int, activeVehicles: int}
     */
    public function compose(CurrentProviderContext $context): array
    {
        $timezone = $context->provider()->organization?->timezone ?: (string) config('app.timezone');
        $today = now($timezone)->toDateString();

        return [
            'toursToday' => $this->tours($context)
                ->whereDate('tour_date', $today)
                ->whereNotIn('status', [TourStatus::DRAFT->value, TourStatus::CANCELLED->value])
                ->count(),
            'toursInProgress' => $this->tours($context)->where('status', TourStatus::IN_PROGRESS->value)->count(),
            'toursCompletedToday' => $this->tours($context)
                ->whereDate('tour_date', $today)
                ->where('status', TourStatus::COMPLETED->value)
                ->count(),
            'activeDrivers' => Driver::where('organization_id', $context->organizationId())
                ->where('provider_id', $context->providerId())
                ->where('status', self::ACTIVE)
                ->count(),
            'activeVehicles' => Vehicle::where('organization_id', $context->organizationId())
                ->where('provider_id', $context->providerId())
                ->where('status', self::ACTIVE)
                ->count(),
        ];
    }

    /**
     * @return Builder<Tour>
     */
    private function tours(CurrentProviderContext $context): Builder
    {
        return Tour::where('organization_id', $context->organizationId())->where('provider_id', $context->providerId());
    }
}
