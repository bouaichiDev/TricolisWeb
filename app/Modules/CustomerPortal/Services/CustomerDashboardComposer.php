<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSources;
use App\Modules\Dashboard\Services\DashboardPeriod;
use App\Modules\Identity\Services\RoleDashboardWidgets;
use App\Shared\Dashboard\Catalogue\CustomerPortalWidgets;
use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetRegistry;
use App\Shared\Enums\RoleScope;

/**
 * Le tableau de bord d'un membre du portail — le moteur interne, appliqué au
 * client courant.
 *
 * Mêmes trois filtres que `DashboardComposer`, dans le même ordre :
 *
 * 1. **les rôles** — union des widgets que ses rôles `CUSTOMER_*` montrent,
 *    tels que le transporteur les a configurés (`role_dashboard_configurations`) ;
 * 2. **les permissions effectives** du membre — un widget refusé n'est ni
 *    calculé, ni présent dans la réponse ;
 * 3. **l'ordre** — plus petit rang configuré.
 *
 * Et une garantie de plus : le contexte porte le client, et la source des
 * widgets du portail refuse de calculer sans lui.
 */
final readonly class CustomerDashboardComposer
{
    public function __construct(
        private CurrentCustomerContext $context,
        private DashboardDataSources $sources,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function compose(?DashboardPeriod $period = null): array
    {
        $positions = $this->selection();
        $widgets = [];

        foreach ($positions as $key => $position) {
            $widget = DashboardWidgetRegistry::findIn(RoleScope::CUSTOMER, $key);

            if ($widget !== null && $this->context->can($widget->requiredPermission)) {
                $widgets[] = $widget;
            }
        }

        $data = $this->sources->resolve($widgets, DashboardContext::forCustomer(
            $this->context->organizationId(),
            $this->context->customerId(),
            $period,
        ));

        return array_map(static fn (DashboardWidget $widget): array => [
            'key' => $widget->key,
            'type' => $widget->type->value,
            'label' => CustomerPortalWidgets::label($widget->key),
            'size' => $widget->size->value,
            'position' => $positions[$widget->key],
            'route' => $widget->route,
            'periodAware' => $widget->periodAware,
            'drilldown' => $widget->drilldown?->toArray(),
            'data' => $data[$widget->key] ?? null,
        ], $widgets);
    }

    /**
     * @return array<string, int> clé → rang, triée
     */
    private function selection(): array
    {
        $membership = $this->context->membership()->loadMissing('roles');
        $organizationId = $this->context->organizationId();
        $positions = [];

        foreach ($membership->roles as $role) {
            if (! CustomerPermissions::countsFor($role, $organizationId)) {
                continue;
            }

            foreach (RoleDashboardWidgets::for($role->id, RoleScope::CUSTOMER)->all() as $key => $position) {
                $positions[$key] = min($positions[$key] ?? PHP_INT_MAX, $position);
            }
        }

        uksort($positions, static fn (string $a, string $b): int => [$positions[$a], $a] <=> [$positions[$b], $b]);

        return $positions;
    }
}
