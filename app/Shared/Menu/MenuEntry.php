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
 * Ce qu'une organisation choisit, c'est la **visibilité** et l'**ordre** de ces
 * entrées, stockés dans `organization_menu_items`.
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
     * @return array<string, mixed>
     */
    public function toArray(bool $isVisible, int $position): array
    {
        return [
            'code' => $this->code,
            'labelKey' => $this->labelKey,
            'icon' => $this->icon,
            'route' => $this->route,
            'permission' => $this->permission,
            'parent' => $this->parent,
            'section' => $this->section->value,
            'position' => $position,
            'isVisible' => $isVisible,
            'canHide' => ! $this->alwaysVisible,
        ];
    }
}
