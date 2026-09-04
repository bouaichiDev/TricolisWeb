<?php

declare(strict_types=1);

namespace App\Shared\Menu;

use App\Shared\Enums\MenuSection;
use App\Shared\Enums\RoleScope;

/**
 * Une entrée du catalogue de menu.
 *
 * Tout ce qu'elle porte est **couplé au code** : `route` doit exister dans le
 * routeur React, `icon` est le nom d'un composant, `labelKey` une clé i18n.
 * C'est pourquoi le catalogue reste en code et non en base — une route saisie
 * en base qui ne correspond à rien donnerait « Page introuvable », et une clé
 * i18n inconnue afficherait la clé brute.
 *
 * Ce qu'une organisation choisit — visibilité, ordre, libellé, icône et
 * rattachement — est stocké dans `organization_menu_items` et vient se poser
 * **par-dessus** ces valeurs. Le catalogue reste donc le défaut : une organisation qui n'a rien
 * renommé suit les traductions livrées, y compris les futures.
 */
final readonly class MenuEntry
{
    public function __construct(
        public string $code,
        public string $labelKey,
        public string $icon,
        public MenuSection $section,
        public int $position,
        public ?string $route = null,
        public ?string $permission = null,
        public ?string $parent = null,
        /** Portée : une entrée plateforme n'est jamais proposée à un organisme. */
        public RoleScope $scope = RoleScope::ORGANIZATION,
        /**
         * Entrée que l'organisation ne peut pas masquer.
         *
         * Sans elle, un administrateur pourrait se couper l'accès à
         * l'administration et n'aurait plus aucun écran pour revenir en
         * arrière.
         */
        public bool $alwaysVisible = false,
    ) {}

    public function isGroup(): bool
    {
        return $this->route === null;
    }

    /**
     * Projection destinée au frontend, réglages de l'organisation appliqués.
     *
     * `icon` et `parent` sont **effectifs** : la barre latérale n'a pas à savoir
     * d'où ils viennent. `label` en revanche reste distinct de `labelKey` — null
     * dit « traduis la clé », et confondre les deux ferait perdre la
     * traduction dès qu'une langue s'ajouterait.
     *
     * `parent` a besoin d'un drapeau à part parce que `null` y est une valeur
     * choisie — « au premier niveau » — et non une absence de choix. Sans lui,
     * une entrée qu'on vient de sortir de son groupe y retournerait aussitôt.
     *
     * `canReparent` dit à l'écran de réglage ce qu'il peut proposer : un groupe
     * ne se déplace pas d'un niveau, faute de troisième niveau où mettre ses
     * entrées.
     *
     * `canHide` est ici le point de vue du **catalogue** — « cette entrée ne
     * devrait pas se masquer ». `RoleMenuCatalogue` le recalcule pour le rôle
     * qu'il sert : depuis que le menu appartient au rôle, seul le rôle système,
     * qui ne se modifie pas, garde ces entrées.
     *
     * `isCustom` se déduit de la section plutôt que d'un drapeau de plus :
     * `MenuSection::CUSTOM` n'appartient qu'aux groupes qu'une organisation
     * s'est créés, et aucune entrée du catalogue ne la porte. C'est ce qui dit
     * à l'écran qu'elle peut proposer de supprimer ce groupe — un groupe livré,
     * lui, ne se supprime pas.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        bool $isVisible,
        int $position,
        ?string $label = null,
        ?string $icon = null,
        bool $parentOverridden = false,
        ?string $parent = null,
    ): array {
        return [
            'code' => $this->code,
            'labelKey' => $this->labelKey,
            'label' => $label,
            'icon' => $icon ?? $this->icon,
            'route' => $this->route,
            'permission' => $this->permission,
            'parent' => $parentOverridden ? $parent : $this->parent,
            'canReparent' => ! $this->isGroup(),
            'section' => $this->section->value,
            'position' => $position,
            'isVisible' => $isVisible,
            'canHide' => ! $this->alwaysVisible,
            'isCustom' => $this->section === MenuSection::CUSTOM,
        ];
    }
}
