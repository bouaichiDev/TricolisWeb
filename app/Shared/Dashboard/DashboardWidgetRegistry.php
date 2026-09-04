<?php

declare(strict_types=1);

namespace App\Shared\Dashboard;

use App\Shared\Dashboard\Catalogue\AdministrationWidgets;
use App\Shared\Dashboard\Catalogue\BillingWidgets;
use App\Shared\Dashboard\Catalogue\ClaimsWidgets;
use App\Shared\Dashboard\Catalogue\CommunicationsWidgets;
use App\Shared\Dashboard\Catalogue\IntegrationsWidgets;
use App\Shared\Dashboard\Catalogue\OperationsWidgets;
use App\Shared\Dashboard\Catalogue\PlanningWidgets;
use App\Shared\Dashboard\Catalogue\QuickActionWidgets;
use App\Shared\Dashboard\Catalogue\StockWidgets;

/**
 * Catalogue des widgets livrés avec l'application. Source unique.
 *
 * **Il n'y a pas de table `dashboard_widgets`, et il n'y en aura pas.** Ce que
 * porte une définition — la clé qui désigne un résolveur, le type qui désigne
 * un composant, la permission qui doit exister dans le référentiel, la route
 * qui doit exister dans le routeur React — est couplé au code et se déploie
 * avec lui. Une table aurait permis d'écrire une clé sans résolveur, une
 * permission qui n'existe pas, une route qui mène à « Page introuvable » : trois
 * pannes silencieuses pour aucun gain, puisque personne n'ajoute un widget sans
 * écrire le code qui le calcule.
 *
 * La base ne porte donc **que la sélection d'un rôle**, dans
 * `role_dashboard_configurations`.
 *
 * C'est la même ligne de partage que pour le menu, et pour les mêmes raisons :
 * voir `docs/backend/role-menu.md`, §2.
 *
 * Ajouter un widget se fait en trois gestes : la définition dans le fichier de
 * sa catégorie, le calcul dans la source de données correspondante, les deux
 * clés i18n dans `fr.json`. Aucune migration, aucune synchronisation —
 * l'absence de ligne vaut « les défauts du catalogue », et l'écran de réglage
 * le propose aussitôt à tous les rôles.
 */
final class DashboardWidgetRegistry
{
    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            ...OperationsWidgets::all(),
            ...PlanningWidgets::all(),
            ...ClaimsWidgets::all(),
            ...BillingWidgets::all(),
            ...StockWidgets::all(),
            ...CommunicationsWidgets::all(),
            ...IntegrationsWidgets::all(),
            ...AdministrationWidgets::all(),
            ...QuickActionWidgets::all(),
        ];
    }

    /**
     * Le catalogue indexé par clé.
     *
     * Mémorisé pour la durée de la requête : `find()` est appelé une fois par
     * widget configuré, et reconstruire la liste à chaque appel aurait fait de
     * la validation d'une configuration une opération quadratique.
     *
     * @return array<string, DashboardWidget>
     */
    public static function byKey(): array
    {
        static $index = null;

        if ($index === null) {
            $index = [];

            foreach (self::all() as $widget) {
                $index[$widget->key] = $widget;
            }
        }

        return $index;
    }

    public static function find(string $key): ?DashboardWidget
    {
        return self::byKey()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return self::find($key) !== null;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::byKey());
    }

    /**
     * Widgets servis à un rôle qui n'a **rien** configuré.
     *
     * À ne pas confondre avec un rôle dont la configuration est vide : celui-là
     * a choisi de ne rien voir, et `RoleDashboardWidgets` tient la distinction.
     *
     * @return array<int, DashboardWidget>
     */
    public static function defaults(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (DashboardWidget $widget): bool => $widget->defaultEnabled,
        ));
    }
}
