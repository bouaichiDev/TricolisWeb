<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Role;
use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetRegistry;

/**
 * Le catalogue tel que l'écran de réglage d'un rôle l'affiche.
 *
 * Distinct de `DashboardComposer`, qui compose le tableau de bord **servi** :
 * ici on montre tout ce qui est réglable, là on montre ce qui est autorisé. Et
 * la divergence est volontaire sur un point : **les widgets qu'un rôle n'a pas
 * le droit de voir restent affichés**, interrupteur désactivé et permission
 * manquante en toutes lettres.
 *
 * Les masquer aurait paru plus propre. C'aurait surtout été muet : un
 * administrateur qui cherche « Factures brouillon » et ne le trouve pas conclut
 * que le widget n'existe pas, alors qu'il lui manque `invoices.view` sur ce
 * rôle. Le dire lui indique le geste suivant — accorder la permission, dans
 * l'onglet d'à côté.
 *
 * **Cet écran n'accorde jamais rien.** C'est la règle du projet : le tableau de
 * bord range, les permissions protègent. Un interrupteur qui aurait ajouté la
 * permission manquante aurait fait de la configuration du tableau de bord une
 * voie d'élévation, ouverte à qui détient `dashboard.configure`.
 */
final readonly class RoleDashboardCatalogue
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forRole(Role $role): array
    {
        $chosen = RoleDashboardWidgets::for($role->id);
        $granted = $this->permissionsOf($role);

        $items = array_map(
            static fn (DashboardWidget $widget): array => $widget->toCatalogueArray(
                $chosen->isEnabled($widget),
                $chosen->positionOf($widget),
                in_array($widget->requiredPermission, $granted, true),
            ),
            DashboardWidgetRegistry::all(),
        );

        // Trié comme le tableau de bord le sera : l'écran de réglage montre
        // l'ordre qu'il produit, et non l'ordre du catalogue. Le départage par
        // clé évite qu'un rang partagé rende la liste instable d'un
        // rechargement à l'autre.
        usort($items, static fn (array $a, array $b): int => [$a['position'], $a['key']] <=> [$b['position'], $b['key']]);

        return $items;
    }

    /**
     * Les permissions du rôle, et rien de plus.
     *
     * Pas celles de l'utilisateur qui règle : un propriétaire d'organisme
     * détient tout, et se servir de ses droits ici aurait rendu chaque
     * interrupteur actif — y compris ceux qui ne produiront jamais rien pour
     * les porteurs du rôle.
     *
     * @return array<int, string>
     */
    private function permissionsOf(Role $role): array
    {
        return $role->permissions()->pluck('code')->all();
    }
}
