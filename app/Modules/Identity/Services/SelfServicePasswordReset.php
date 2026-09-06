<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\UserStatus;

/**
 * Qui peut réinitialiser son mot de passe tout seul.
 *
 * **Les administrateurs, et eux seuls.** Un chauffeur ou un exploitant qui perd
 * son mot de passe s'adresse à son administrateur, qui lui renvoie l'accès
 * depuis sa fiche — ce chemin existe déjà, avec ses deux variantes :
 * `MemberPasswordController::sendLink()` et `::set()`.
 *
 * La raison est celle qui vaut pour toute la plateforme : le formulaire public
 * déclenche un courriel vers une adresse que l'appelant choisit, sans que
 * personne n'ait rien vérifié. Restreint aux administrateurs, il ne concerne
 * plus qu'une poignée de comptes, dont on sait qui ils sont ; les autres
 * passent par quelqu'un qui les connaît, et c'est cette reconnaissance qui
 * remplace la vérification que le formulaire ne fait pas.
 *
 * **« Administrateur » n'est pas un nom de rôle.** C'est `users.reset_password`
 * — le droit de rendre son accès à quelqu'un d'autre —, plus le propriétaire
 * d'un organisme, qui détient tout chez lui, plus un compte plateforme. Se fier
 * au libellé « Admin » ferait d'un renommage un octroi de droits.
 */
final readonly class SelfServicePasswordReset
{
    /** Le droit qui définit un administrateur, ici comme dans les écrans de membres. */
    private const string PERMISSION = 'users.reset_password';

    public function __construct(private PlatformAccess $platform) {}

    public function isAllowedFor(User $user): bool
    {
        if ($user->status !== UserStatus::ACTIVE) {
            return false;
        }

        if ($this->platform->isPlatformAdmin($user)) {
            return true;
        }

        $memberships = OrganizationUser::where('user_id', $user->id)
            ->where('status', UserStatus::ACTIVE->value)
            ->with('roles.permissions:id,code')
            ->get();

        foreach ($memberships as $membership) {
            // Le proprietaire detient tout chez lui sans passer par un role :
            // c'est la regle appliquee partout ailleurs, reprise telle quelle.
            if ($membership->is_owner) {
                return true;
            }

            if ($this->holdsPermission($membership)) {
                return true;
            }
        }

        return false;
    }

    private function holdsPermission(OrganizationUser $membership): bool
    {
        return $membership->roles->contains(
            fn (Role $role): bool => $role->permissions->contains(
                fn (Permission $permission): bool => $permission->code === self::PERMISSION,
            ),
        );
    }
}
