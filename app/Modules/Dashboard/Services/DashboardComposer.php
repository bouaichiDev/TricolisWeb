<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\EffectivePermissions;
use App\Modules\Identity\Services\UserDashboardWidgets;
use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetRegistry;

/**
 * Le tableau de bord servi à quelqu'un.
 *
 * Trois filtres, **dans cet ordre**, et le dernier ne se négocie pas :
 *
 * 1. **les rôles** — union des widgets que ses rôles montrent dans
 *    l'organisation active ;
 * 2. **les permissions effectives** — un widget dont il n'a pas le droit de
 *    lire la source n'est pas calculé ;
 * 3. **l'ordre** — plus petit rang configuré, la clé départageant les égalités.
 *
 * Le deuxième est le cœur de la sécurité de cet écran, et il ne se contente pas
 * de masquer : **le widget refusé n'est pas calculé, et sa donnée ne figure pas
 * dans la réponse**. Un widget masqué côté frontend dont la valeur voyagerait
 * quand même dans le JSON serait une fuite complète — il suffirait d'ouvrir
 * l'onglet réseau. La configuration propose ; la permission dispose.
 *
 * La conséquence utile est immédiate : retirer `customers.view` à un rôle fait
 * disparaître `customers_count` au prochain chargement, sans que personne ait à
 * toucher à la configuration du tableau de bord. Les deux réglages restent
 * indépendants, et c'est le second qui protège.
 */
final readonly class DashboardComposer
{
    public function __construct(private DashboardDataSources $sources) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function compose(User $user, string $organizationId): array
    {
        $selection = UserDashboardWidgets::for($user->id, $organizationId);
        $granted = EffectivePermissions::for($user->id, $organizationId);

        $widgets = [];

        foreach ($selection->orderedKeys() as $key) {
            $widget = DashboardWidgetRegistry::find($key);

            if ($widget !== null && $granted->allows($widget->requiredPermission)) {
                $widgets[] = $widget;
            }
        }

        $data = $this->sources->resolve($widgets, DashboardContext::forOrganization($organizationId));

        return array_map(
            static fn (DashboardWidget $widget): array => [
                'key' => $widget->key,
                'type' => $widget->type->value,
                'labelKey' => $widget->labelKey(),
                'size' => $widget->size->value,
                'position' => $selection->positionOf($widget->key),
                'route' => $widget->route,
                'data' => $data[$widget->key] ?? null,
            ],
            $widgets,
        );
    }
}
