<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\CustomerPortal\Models\CustomerUser;
use Illuminate\Validation\ValidationException;

/**
 * Ce qu'un administrateur du portail ne peut pas faire à un membre,
 * quelles que soient ses permissions.
 *
 * - **agir sur soi-même** : se désactiver ou se retirer ses propres rôles
 *   laisserait un portail sans administrateur, et s'ajouter des rôles
 *   contournerait le plafond de délégation ;
 * - **agir sur le propriétaire** : le compte désigné par le transporteur
 *   reste la porte d'entrée du client, et seul le transporteur peut le
 *   retirer.
 *
 * L'appartenance au même client est garantie en amont, par la résolution de
 * la route dans le Customer courant.
 */
final class ManagedMemberGuard
{
    /**
     * @throws ValidationException
     */
    public static function ensureManageable(CustomerUser $actor, CustomerUser $target): void
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages(['member' => ['Vous ne pouvez pas effectuer cette action sur votre propre compte.']]);
        }

        if ($target->is_owner) {
            throw ValidationException::withMessages(['member' => ['Le compte propriétaire du portail ne peut être modifié que par votre transporteur.']]);
        }
    }
}
