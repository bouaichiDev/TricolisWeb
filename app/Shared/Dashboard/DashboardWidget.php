<?php

declare(strict_types=1);

namespace App\Shared\Dashboard;

/**
 * Une définition de widget, telle que le code la livre.
 *
 * Tout ce qu'elle porte est **couplé au code** : `key` désigne un résolveur,
 * `type` un composant React, `requiredPermission` une entrée du référentiel, et
 * `route` un chemin du routeur. C'est la raison pour laquelle le catalogue
 * n'est pas une table — la même que pour le menu : une clé saisie en base qui
 * ne correspond à rien afficherait une carte vide, une permission inventée
 * n'aurait aucun effet, et un nom de composant venu de la base ouvrirait la
 * porte à n'importe quel rendu.
 *
 * Ce qu'un rôle en fait — quels widgets, dans quel ordre — vit en base, dans
 * `role_dashboard_configurations`, et rien d'autre.
 *
 * `requiredPermission` n'est **jamais** nullable. Un widget sans permission
 * serait visible de tous, y compris de rôles qui n'ont pas le droit d'ouvrir
 * l'écran d'où le chiffre vient : le tableau de bord le leur donnerait quand
 * même. Chaque widget en porte donc une, et c'est elle qui décide — la
 * configuration ne fait que proposer.
 *
 * `route` reste facultatif, et le rester est délibéré : un widget qui compte
 * des services n'a pas d'écran de destination — les services se lisent dans
 * leur commande. Y écrire `/services` mènerait au catalogue des prestations,
 * qui n'a aucun rapport.
 */
final readonly class DashboardWidget
{
    public function __construct(
        public string $key,
        public DashboardWidgetType $type,
        public DashboardWidgetCategory $category,
        /** Permission sans laquelle le widget n'est ni proposé ni calculé. */
        public string $requiredPermission,
        public int $defaultPosition,
        public DashboardWidgetSize $size = DashboardWidgetSize::SMALL,
        /**
         * Widget servi aux rôles qui n'ont **rien** configuré.
         *
         * L'absence de ligne vaut « les défauts du catalogue » — un rôle créé
         * après l'ajout d'un widget en profite sans migration de données. Une
         * ligne présente et vide veut dire autre chose : voir
         * `RoleDashboardWidgets`.
         */
        public bool $defaultEnabled = false,
        /** Écran que la carte ouvre, s'il en existe un. */
        public ?string $route = null,
    ) {}

    /**
     * Clé i18n du libellé, et de la description.
     *
     * Déduites de la clé du widget plutôt que saisies : deux champs de plus par
     * définition, sur une cinquantaine d'entrées, auraient fini par diverger de
     * la clé qu'ils décrivent. Un test vérifie que les deux existent réellement
     * dans `fr.json` — une clé absente afficherait la clé brute.
     */
    public function labelKey(): string
    {
        return "dashboardWidgets.{$this->key}.label";
    }

    public function descriptionKey(): string
    {
        return "dashboardWidgets.{$this->key}.description";
    }

    /**
     * Projection destinée à l'écran de réglage d'un rôle.
     *
     * `availableForRole` est le point central de cet écran : il dit si le rôle
     * détient `requiredPermission`. Faux, l'interrupteur est désactivé et la
     * permission manquante affichée — **jamais accordée**. Le tableau de bord
     * ne distribue pas de droits, il en dépend.
     *
     * Ni résolveur, ni requête, ni classe PHP ne figurent ici : ce que l'écran
     * n'a pas besoin de connaître, il ne le reçoit pas.
     *
     * @return array<string, mixed>
     */
    public function toCatalogueArray(bool $isEnabled, int $position, bool $availableForRole): array
    {
        return [
            'key' => $this->key,
            'labelKey' => $this->labelKey(),
            'descriptionKey' => $this->descriptionKey(),
            'category' => $this->category->value,
            'type' => $this->type->value,
            'size' => $this->size->value,
            'requiredPermission' => $this->requiredPermission,
            'defaultPosition' => $this->defaultPosition,
            'position' => $position,
            'isEnabled' => $isEnabled,
            'availableForRole' => $availableForRole,
        ];
    }
}
