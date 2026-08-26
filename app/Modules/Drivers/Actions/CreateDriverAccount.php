<?php

declare(strict_types=1);

namespace App\Modules\Drivers\Actions;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Crée le compte d'un chauffeur, et le rattache à l'organisation.
 *
 * Tout chauffeur créé depuis l'application a son compte : c'est ce qui permet
 * de savoir, plus tard, qui a fait quoi. Le créer à part, dans un second écran,
 * laisserait des chauffeurs sans compte selon la rigueur de qui saisit.
 *
 * **Le mot de passe est tiré au hasard et n'est envoyé nulle part.** Le compte
 * naît « invité » : il devient utilisable quand son détenteur passe par la
 * réinitialisation, comme tout membre invité. Fabriquer un mot de passe
 * prévisible — le code du chauffeur, sa date de naissance — reviendrait à ne
 * pas en avoir.
 *
 * Le rôle chauffeur est attaché ici plutôt que par `CreateOrganizationMember`,
 * qui refuse les rôles système afin d'empêcher une élévation par l'API des
 * utilisateurs. Ce rôle-là n'ouvre rien : il n'a aucune permission.
 */
final readonly class CreateDriverAccount
{
    /**
     * @param  array{firstName: string, lastName: string, email: string, phone?: string|null}  $data
     */
    public function execute(array $data, string $organizationId): User
    {
        $role = Role::where('organization_id', $organizationId)
            ->where('code', RoleSeeder::DRIVER_CODE)
            ->first();

        if ($role === null) {
            throw new RuntimeException(
                'Le rôle chauffeur manque à cette organisation. Exécutez `php artisan db:seed --class=RoleSeeder`.',
            );
        }

        return DB::transaction(function () use ($data, $organizationId, $role): User {
            $user = User::create([
                'first_name' => $data['firstName'],
                'last_name' => $data['lastName'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Str::password(32),
                'preferred_language' => 'fr',
                'status' => UserStatus::INVITED->value,
            ]);

            $membership = OrganizationUser::create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'is_owner' => false,
                'is_primary' => true,
                'status' => UserStatus::INVITED->value,
                'joined_at' => now(),
            ]);

            UserRole::create(['organization_user_id' => $membership->id, 'role_id' => $role->id]);

            return $user;
        });
    }
}
