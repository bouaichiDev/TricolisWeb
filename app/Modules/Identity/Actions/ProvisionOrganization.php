<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\PlatformAccess;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Types\Actions\SeedSystemTypes;
use App\Shared\Enums\OrganizationStatus;
use App\Shared\Enums\RoleScope;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fait naître une organisation et son administrateur, d'un seul geste.
 *
 * **Deux portes mènent ici, et une seule transaction les sert** : l'inscription
 * publique d'un transporteur, et l'acceptation par la plateforme d'une demande
 * d'accès. Les deux doivent produire exactement la même chose — une
 * organisation, un compte propriétaire, un rôle d'administrateur privé des
 * permissions plateforme, et les référentiels sans lesquels le back-office est
 * inutilisable. Les écrire deux fois, c'est en oublier une des deux le jour où
 * la liste change.
 *
 * Ce qui les distingue reste chez l'appelant : l'inscription rend un jeton et
 * ouvre la session tout de suite ; l'acceptation, elle, n'en rend aucun et
 * envoie un lien par courriel — personne n'est devant l'écran à ce moment-là.
 */
final readonly class ProvisionOrganization
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, organization: Organization}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            /** @var array<string, mixed> $organizationData */
            $organizationData = $data['organization'];

            $user = User::create([
                'first_name' => $data['firstName'],
                'last_name' => $data['lastName'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'preferred_language' => $data['preferredLanguage'] ?? 'fr',
                'status' => UserStatus::ACTIVE,
            ]);

            $organization = Organization::create([
                'code' => $organizationData['code'] ?? $this->uniqueOrganizationCode($organizationData['name']),
                'name' => $organizationData['name'],
                'legal_name' => $organizationData['legalName'] ?? null,
                'registration_number' => $organizationData['registrationNumber'] ?? null,
                'tax_number' => $organizationData['taxNumber'] ?? null,
                'email' => $organizationData['email'] ?? $data['email'],
                'phone' => $organizationData['phone'] ?? ($data['phone'] ?? null),
                'preferred_language' => $organizationData['preferredLanguage'] ?? ($data['preferredLanguage'] ?? 'fr'),
                'timezone' => $organizationData['timezone'] ?? 'Africa/Casablanca',
                'currency_code' => $organizationData['currencyCode'] ?? 'MAD',
                'status' => OrganizationStatus::ACTIVE,
                'settings' => [],
            ]);

            $organizationUser = OrganizationUser::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'is_owner' => true,
                'is_primary' => true,
                'status' => UserStatus::ACTIVE,
                'joined_at' => now(),
            ]);

            $this->grantAdminRole($organization, $organizationUser);

            // Même menu de base qu'une organisation créée par la plateforme :
            // sans ses sources structurelles, l'organisation ne pourrait ni
            // creer un vehicule ni classer un colis.
            app(SeedSystemTypes::class)->execute($organization->id);

            return ['user' => $user, 'organization' => $organization];
        });
    }

    private function grantAdminRole(Organization $organization, OrganizationUser $organizationUser): void
    {
        $adminRole = Role::create([
            'organization_id' => $organization->id,
            'code' => 'admin',
            'name' => 'Administrateur',
            'scope' => RoleScope::ORGANIZATION->value,
            'is_system' => true,
            'status' => 'active',
        ]);

        // Les permissions réservées à la plateforme sont écartées. Sans ce
        // retrait, toute inscription au formulaire public produisait un compte
        // capable de créer et de supprimer des organisations : il suffisait de
        // s'inscrire pour administrer Tricolis.
        $organizationalPermissions = Permission::whereNotIn('code', PlatformAccess::PLATFORM_PERMISSIONS)->pluck('id');

        foreach ($organizationalPermissions as $permissionId) {
            RolePermission::create([
                'role_id' => $adminRole->id,
                'permission_id' => $permissionId,
            ]);
        }

        UserRole::create([
            'organization_user_id' => $organizationUser->id,
            'role_id' => $adminRole->id,
        ]);
    }

    private function uniqueOrganizationCode(string $name): string
    {
        $base = Str::slug($name) ?: 'transporteur';
        $code = $base;
        $suffix = 1;

        while (Organization::where('code', $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
