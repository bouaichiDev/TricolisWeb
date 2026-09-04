<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\RoleScope;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie qu'un rôle peut être attribué à un membre.
 *
 * La vérification qui existait ne portait que sur l'organisation. Elle laissait
 * passer deux cas :
 *
 * - un **rôle système**, qui porte l'intégralité des permissions de son
 *   organisation — l'attribuer revenait à conférer des droits que l'attribuant
 *   ne détient pas ;
 * - un **rôle plateforme**, sans organisation, donc jamais égal à
 *   l'organisation active mais absent de la comparaison lorsque `roleIds` était
 *   vide de vérification préalable.
 *
 * Le refus prend la forme d'un 422 sur `roleIds`. Un 404 conviendrait pour un
 * rôle d'une autre organisation, mais la validation groupée ne permet pas de
 * distinguer les deux causes sans révéler laquelle s'applique — et c'est
 * précisément ce qu'il faut éviter.
 */
class RoleAssignmentGuard
{
    public function __construct(private readonly PlatformAccess $platform) {}

    /**
     * @param  array<int, string>  $roleIds
     *
     * @throws ValidationException
     */
    public function assertAssignable(?User $actor, string $organizationId, array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        $allowed = $this->platform->assignableRoles($actor, $organizationId)->pluck('id')->all();
        $refused = array_values(array_diff($roleIds, $allowed));

        if ($refused === []) {
            return;
        }

        throw ValidationException::withMessages([
            'roleIds' => ['Un ou plusieurs rôles ne sont pas attribuables dans cette organisation.'],
        ]);
    }

    /**
     * Un rôle est-il local, non système, et rattaché à cette organisation ?
     *
     * Utilisé comme seconde barrière là où l'auteur de l'appel n'est pas connu.
     */
    public function isOrganizationRole(Role $role, string $organizationId): bool
    {
        return $role->organization_id === $organizationId
            && ! $role->is_system
            && RoleScope::tryFromValue($role->scope) === RoleScope::ORGANIZATION;
    }
}
