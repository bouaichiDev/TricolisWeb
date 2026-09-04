<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Planning\Services\PlanningEligibility;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tournées, et ce qui n'y est pas encore.
 *
 * Les deux widgets de service reprennent `PlanningEligibility::PLANNABLE_STATUSES`
 * plutôt que d'énumérer des statuts. C'est la règle qu'applique la
 * planification elle-même, et le pool qui la sert : trois définitions du même
 * « à planifier » auraient divergé au premier statut ajouté, et le tableau de
 * bord aurait annoncé un travail que l'écran de planification ne proposait pas.
 */
final readonly class PlanningData implements DashboardDataSource
{
    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function resolve(array $keys, DashboardContext $context): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->resolveOne($key, $context);
        }

        return $data;
    }

    private function resolveOne(string $key, DashboardContext $context): mixed
    {
        return match ($key) {
            'tours_today' => DashboardPayload::kpi(
                $this->tours($context)->where('tour_date', $context->today->toDateString())->count()
            ),
            'draft_tours' => DashboardPayload::kpi(
                $this->tours($context)->where('status', TourStatus::DRAFT->value)->count()
            ),

            // « Planifiée » et « confirmée » sont deux états d'une même
            // attente : la tournée existe, elle n'est pas partie. Les séparer
            // aurait donné deux tuiles qu'on additionne de tête.
            'planned_tours' => DashboardPayload::kpi(
                $this->tours($context)->whereIn('status', [
                    TourStatus::PLANNED->value,
                    TourStatus::CONFIRMED->value,
                ])->count()
            ),

            'tours_in_progress' => DashboardPayload::kpi(
                $this->tours($context)->where('status', TourStatus::IN_PROGRESS->value)->count()
            ),
            'completed_tours_today' => DashboardPayload::kpi(
                $this->tours($context)
                    ->where('status', TourStatus::COMPLETED->value)
                    ->where('tour_date', $context->today->toDateString())
                    ->count()
            ),

            'unplanned_services' => DashboardPayload::kpi($this->plannable($context)->count()),
            'services_without_gps' => DashboardPayload::alert($this->withoutCoordinates($context)),

            'recent_tours' => DashboardPayload::list($this->recentTours($context)),
            'tours_by_status' => DashboardPayload::chart($this->toursByStatus($context), MorphMap::TOUR),

            default => null,
        };
    }

    /**
     * @return Builder<Tour>
     */
    private function tours(DashboardContext $context): Builder
    {
        return Tour::query()->where('organization_id', $context->organizationId);
    }

    /**
     * Les services qui attendent une tournée.
     *
     * Deux conditions, celles de la planification : un statut qui l'autorise, et
     * aucune affectation active. Un service peut avoir été planifié dix fois
     * dans son histoire — seule l'affectation en cours compte.
     *
     * @return Builder<OrderService>
     */
    private function plannable(DashboardContext $context): Builder
    {
        return OrderService::query()
            ->whereIn('status', PlanningEligibility::PLANNABLE_STATUSES)
            ->whereHas('order', fn (Builder $order) => $order->where('organization_id', $context->organizationId))
            ->whereDoesntHave('tourStopServices', fn ($assignments) => $assignments->where('is_active_assignment', true));
    }

    /**
     * Services en attente dont l'adresse n'a pas de coordonnées.
     *
     * Ils ne bloquent rien : une tournée les accepte. Mais ils ne peuvent ni
     * être placés sur la carte, ni entrer dans un calcul de distance — d'où
     * l'alerte, qui invite à compléter l'adresse avant la planification plutôt
     * qu'après.
     */
    private function withoutCoordinates(DashboardContext $context): int
    {
        return $this->plannable($context)
            // Le groupe parenthese n'est pas cosmetique : sans lui, le `OR`
            // se lierait a la contrainte de relation posee par `whereHas`, et
            // la sous-requete rendrait toutes les adresses sans longitude,
            // celles des autres services comprises.
            ->whereHas('address', fn (Builder $address) => $address->where(
                fn (Builder $missing) => $missing->whereNull('latitude')->orWhereNull('longitude')
            ))
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentTours(DashboardContext $context): array
    {
        return $this->tours($context)
            ->orderByDesc('tour_date')
            ->limit(6)
            ->get(['id', 'tour_number', 'tour_date', 'status', 'total_customers'])
            ->map(static fn (Tour $tour): array => [
                'id' => $tour->getKey(),
                'title' => $tour->getAttribute('tour_number'),
                'subtitle' => null,
                'status' => $tour->getAttribute('status'),
                'statusSource' => MorphMap::TOUR,
                'date' => $tour->getAttribute('tour_date')?->toDateString(),
                'route' => '/tours/'.$tour->getKey(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{code: string, value: int}>
     */
    private function toursByStatus(DashboardContext $context): array
    {
        return $this->tours($context)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(static fn (object $row): array => [
                'code' => (string) $row->status,
                'value' => (int) $row->total,
            ])
            ->all();
    }
}
