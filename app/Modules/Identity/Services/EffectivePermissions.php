<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Organizations\Models\OrganizationUser;

/**
 * Les permissions dont un compte dispose réellement dans une organisation.
 *
 * Le projet posait déjà cette question, mais toujours **une permission à la
 * fois** — `CurrentOrganizationContext::hasPermission()` et
 * `BaseOrganizationPolicy::hasPermission()`. Le tableau de bord la pose pour une
 * cinquantaine de widgets d'un coup ; les enchaîner aurait rechargé
 * l'appartenance et ses rôles à chaque tour.
 *
 * Les deux règles existantes sont reprises telles quelles, et il n'y en a pas
 * de troisième :
 *
 * - le **propriétaire** de l'organisation détient tout, sans passer par un rôle ;
 * - les autres tiennent leurs permissions de l'**union** de leurs rôles, ce qui
 *   est déjà la règle appliquée partout ailleurs.
 *
 * Rien ici ne dépend du **nom** d'un rôle. Un rôle appelé « Admin » qui ne
 * porterait aucune permission n'ouvre aucun widget, et c'est ce qui empêche
 * qu'un libellé devienne un droit.
 */
final readonly class EffectivePermissions
{
    /**
     * @param  array<string, true>  $codes  Indexé par code : la question est « en fait-il partie ».
     */
    private function __construct(private array $codes, private bool $isOwner) {}

    public static function for(string $userId, string $organizationId): self
    {
        $membership = OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->with('roles.permissions:id,code')
            ->first();

        if ($membership === null) {
            return new self([], false);
        }

        $codes = [];

        foreach ($membership->roles as $role) {
            foreach ($role->permissions as $permission) {
                $codes[$permission->code] = true;
            }
        }

        return new self($codes, (bool) $membership->is_owner);
    }

    public function allows(string $permission): bool
    {
        return $this->isOwner || isset($this->codes[$permission]);
    }
}
