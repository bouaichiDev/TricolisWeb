<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\RoleScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Crée un utilisateur et son rattachement à une organisation.
 *
 * Un utilisateur sans rattachement serait inatteignable par la plateforme :
 * les deux sont donc créés dans la même transaction, avec ses rôles éventuels.
 */
final readonly class CreateOrganizationMember
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, string $organizationId): OrganizationUser
    {
        $roleIds = $data['roleIds'] ?? [];
        $this->assertRolesBelongToOrganization($roleIds, $organizationId);

        return DB::transaction(function () use ($data, $organizationId, $roleIds): OrganizationUser {
            $user = User::create([
                'first_name' => $data['firstName'],
                'last_name' => $data['lastName'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'preferred_language' => $data['preferredLanguage'],
                'status' => $data['status'],
            ]);

            $membership = OrganizationUser::create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'is_owner' => $data['isOwner'] ?? false,
                'is_primary' => $data['isPrimary'] ?? true,
                'status' => $data['status'],
                'joined_at' => now(),
            ]);

            foreach ($roleIds as $roleId) {
                UserRole::firstOrCreate(['organization_user_id' => $membership->id, 'role_id' => $roleId]);
            }

            return $membership;
        });
    }

    /**
     * Seconde barrière : le rôle appartient à l'organisation, il est local et
     * non système.
     *
     * L'appelant a déjà confronté les rôles au plafond de délégation, qui tient
     * compte de l'auteur. Cette action est appelable hors requête HTTP et ne
     * connaît pas cet auteur ; elle vérifie donc ce qui ne dépend que du rôle.
     *
     * La vérification portait auparavant sur la seule organisation : un rôle
     * système ou plateforme passait.
     *
     * @param  array<int, string>  $roleIds
     */
    private function assertRolesBelongToOrganization(array $roleIds, string $organizationId): void
    {
        validator(
            ['roleIds' => $roleIds],
            ['roleIds.*' => [
                Rule::exists('roles', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('scope', RoleScope::ORGANIZATION->value)
                    ->where('is_system', false),
            ]]
        )->validate();
    }
}
