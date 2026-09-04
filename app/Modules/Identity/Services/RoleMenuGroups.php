<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\RoleMenuGroup;
use App\Shared\Enums\MenuSection;
use App\Shared\Enums\RoleScope;
use App\Shared\Menu\MenuEntry;

/**
 * Les groupes qu'un rôle s'est créés, vus comme des entrées de menu.
 *
 * Ils rejoignent le catalogue au moment du calcul plutôt que de vivre à part :
 * le résolveur et l'écran de réglage n'ont ainsi qu'une seule sorte d'entrée à
 * connaître. Un groupe créé se déplace, se renomme, se masque et se remplit
 * exactement comme un groupe livré.
 *
 * Ce qu'ils ne portent jamais : ni route, ni permission. C'est ce qui autorise
 * leur existence en base — un groupe ne mène nulle part, il range.
 */
final readonly class RoleMenuGroups
{
    /**
     * @param  array<int, RoleMenuGroup>  $rows
     */
    private function __construct(private array $rows) {}

    public static function for(?string $roleId): self
    {
        if ($roleId === null) {
            return new self([]);
        }

        return new self(
            RoleMenuGroup::where('role_id', $roleId)->orderBy('position')->get()->all()
        );
    }

    /**
     * Entrées de menu correspondantes.
     *
     * Le libellé est passé en **surcharge** — comme pour une entrée du
     * catalogue qu'on renomme — et `labelKey` reste une clé de repli
     * traduisible. Un groupe dont le nom aurait disparu afficherait ainsi
     * « Groupe » plutôt qu'une clé brute ou un titre vide.
     *
     * @return array<int, MenuEntry>
     */
    public function entries(): array
    {
        return array_map(
            static fn (RoleMenuGroup $row): MenuEntry => new MenuEntry(
                code: $row->code,
                labelKey: 'menu.customGroup',
                icon: $row->icon,
                section: MenuSection::CUSTOM,
                position: $row->position,
                route: null,
                permission: null,
                parent: null,
                scope: RoleScope::ORGANIZATION,
            ),
            $this->rows,
        );
    }

    /**
     * Réglages portés par la ligne elle-même, et non par `role_menu_items` : un
     * groupe créé n'a pas de valeur de catalogue à surcharger, il **est** sa
     * propre valeur.
     *
     * @return array<string, RoleMenuGroup>
     */
    public function byCode(): array
    {
        $map = [];

        foreach ($this->rows as $row) {
            $map[$row->code] = $row;
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    public function codes(): array
    {
        return array_map(static fn (RoleMenuGroup $row): string => $row->code, $this->rows);
    }
}
