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
 * Une source par catégorie, appelée avec toutes ses clés d'un coup. Le
 * contraire — un résolveur par widget, appelé pour chacun — aurait multiplié
 * les allers-retours vers la même table pour une même page : la répartition par
 * catégorie coïncide de près avec la répartition par table, et c'est ce qui
 * laisse à chaque source la possibilité de grouper ce qui peut l'être.
 *
 * **Deux appels au plus, et jamais un seul quand une période est choisie.** Les
 * clés sont séparées selon ce que leur définition déclare : celles qui suivent
 * la période reçoivent le contexte complet, les autres un contexte d'où la
 * période a été **retirée**. C'est ce qui rend impossible qu'un compteur
 * « Commandes du jour » se retrouve filtré sur un trimestre parce qu'une
 * branche a lu `$context->period` sans que le catalogue l'y autorise : la
 * valeur n'est pas là. Une convention de nommage ou un commentaire auraient
 * demandé qu'on y pense à chaque nouveau widget.
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
        /** @var array<string, array{period: array<int, string>, instant: array<int, string>}> $keysByCategory */
        $keysByCategory = [];

        foreach ($widgets as $widget) {
            $keysByCategory[$widget->category->value] ??= ['period' => [], 'instant' => []];
            $keysByCategory[$widget->category->value][$widget->periodAware ? 'period' : 'instant'][] = $widget->key;
        }

        $instant = $context->withoutPeriod();
        $data = [];

        foreach ($keysByCategory as $category => $keys) {
            $source = $this->sourceFor(DashboardWidgetCategory::from($category));

            if ($source === null) {
                continue;
            }

            if ($keys['period'] !== []) {
                $data += $source->resolve($keys['period'], $context);
            }

            if ($keys['instant'] !== []) {
                $data += $source->resolve($keys['instant'], $instant);
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
