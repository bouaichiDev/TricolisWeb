<?php

declare(strict_types=1);

namespace App\Shared\Menu;

use App\Modules\Identity\Models\RoleMenuGroup;

/**
 * Codes de menu acceptés pour un rôle donné.
 *
 * Deux espaces de noms se rejoignent ici : celui du **catalogue**, livré en
 * code, et celui des **groupes que le rôle s'est créés**, en base. Les requêtes
 * de réglage doivent accepter les deux — sans quoi une entrée rangée dans un
 * groupe créé serait refusée à l'enregistrement — et n'accepter qu'eux : un
 * code inconnu produirait une ligne orpheline.
 *
 * La séparation tient au préfixe porté par `RoleMenuGroup`, ce qui rend la
 * réunion sûre : aucun code créé ne peut coïncider avec un code livré, présent
 * ou futur.
 *
 * Ces listes touchent la base et ne peuvent donc pas vivre dans `MenuCatalogue`,
 * qui est une constante du code.
 */
final class MenuCodes
{
    /**
     * Tout ce qui peut être réglé : entrées du catalogue et groupes créés.
     *
     * @return array<int, string>
     */
    public static function settable(?string $roleId): array
    {
        return [...MenuCatalogue::codes(), ...self::customGroups($roleId)];
    }

    /**
     * Tout ce qui peut **accueillir** une entrée.
     *
     * @return array<int, string>
     */
    public static function groups(?string $roleId): array
    {
        return [...MenuCatalogue::groupCodes(), ...self::customGroups($roleId)];
    }

    /**
     * @return array<int, string>
     */
    public static function customGroups(?string $roleId): array
    {
        if ($roleId === null) {
            return [];
        }

        return RoleMenuGroup::where('role_id', $roleId)->pluck('code')->all();
    }
}
