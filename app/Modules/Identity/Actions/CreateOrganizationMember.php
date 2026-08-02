<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\OrganizationUser;
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
     * @param  array<int, string>  $roleIds
     */
    private function assertRolesBelongToOrganization(array $roleIds, string $organizationId): void
    {
        validator(
            ['roleIds' => $roleIds],
            ['roleIds.*' => [Rule::exists('roles', 'id')->where('organization_id', $organizationId)]]
        )->validate();
    }
}
