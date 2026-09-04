<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Modules\Dashboard\Sources\AdministrationData;
use App\Modules\Dashboard\Sources\BillingData;
use App\Modules\Dashboard\Sources\ClaimsData;
use App\Modules\Dashboard\Sources\CommunicationsData;
use App\Modules\Dashboard\Sources\IntegrationsData;
use App\Modules\Dashboard\Sources\OperationsData;
use App\Modules\Dashboard\Sources\PlanningData;
use App\Modules\Dashboard\Sources\StockData;
use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;

/**
 * Aiguillage des widgets retenus vers ce qui sait les calculer.
 *
 * Une source par catégorie, appelée **une fois** avec toutes ses clés. Le
 * contraire — un résolveur par widget, appelé pour chacun — aurait multiplié
 * les allers-retours vers la même table pour une même page : la répartition par
 * catégorie coïncide de près avec la répartition par table, et c'est ce qui
 * laisse à chaque source la possibilité de grouper ce qui peut l'être.
 *
 * Les actions rapides n'ont pas de source, et ne peuvent pas en avoir : elles
 * ne portent aucun chiffre. Une carte « Nouvelle commande » n'affiche qu'un
 * libellé et une destination, tous deux connus du catalogue. Leur donnée est
 * donc `null`, ce que le frontend sait rendre.
 */
final readonly class DashboardDataSources
{
    public function __construct(
        private OperationsData $operations,
        private PlanningData $planning,
        private ClaimsData $claims,
        private BillingData $billing,
        private StockData $stock,
        private CommunicationsData $communications,
        private IntegrationsData $integrations,
        private AdministrationData $administration,
    ) {}

    /**
     * @param  array<int, DashboardWidget>  $widgets  Déjà filtrés par les permissions.
     * @return array<string, mixed>
     */
    public function resolve(array $widgets, DashboardContext $context): array
    {
        $keysByCategory = [];

        foreach ($widgets as $widget) {
            $keysByCategory[$widget->category->value][] = $widget->key;
        }

        $data = [];

        foreach ($keysByCategory as $category => $keys) {
            $source = $this->sourceFor(DashboardWidgetCategory::from($category));

            if ($source !== null) {
                $data += $source->resolve($keys, $context);
            }
        }

        return $data;
    }

    private function sourceFor(DashboardWidgetCategory $category): ?DashboardDataSource
    {
        return match ($category) {
            DashboardWidgetCategory::OPERATIONS => $this->operations,
            DashboardWidgetCategory::PLANNING => $this->planning,
            DashboardWidgetCategory::CLAIMS => $this->claims,
            DashboardWidgetCategory::BILLING => $this->billing,
            DashboardWidgetCategory::STOCK => $this->stock,
            DashboardWidgetCategory::COMMUNICATIONS => $this->communications,
            DashboardWidgetCategory::INTEGRATIONS => $this->integrations,
            DashboardWidgetCategory::ADMINISTRATION => $this->administration,
            DashboardWidgetCategory::QUICK_ACTIONS => null,
        };
    }
}
