<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleMenuGroup;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\RoleScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function authUser(): User
{
    $user = User::where('email', 'admin@tricolis.dev')->first();

    if ($user === null) {
        throw new RuntimeException('Utilisateur de test introuvable.');
    }

    return $user;
}

function authOrganization(): Organization
{
    $organization = Organization::where('code', 'tricolis-dev')->first();

    if ($organization === null) {
        throw new RuntimeException('Organisation de test introuvable.');
    }

    return $organization;
}

/**
 * Élève un utilisateur au rang d'administrateur de plateforme.
 *
 * Le rôle `superadmin` est semé sans organisation ; le rattacher à une
 * appartenance existante est la seule façon de conférer l'autorité plateforme,
 * et c'est délibérément une action explicite : aucun compte ne l'obtient par
 * défaut.
 */
function makePlatformAdmin(User $user): User
{
    $role = Role::where('scope', RoleScope::PLATFORM->value)->whereNull('organization_id')->firstOrFail();
    $membership = OrganizationUser::where('user_id', $user->id)->firstOrFail();

    UserRole::firstOrCreate(['organization_user_id' => $membership->id, 'role_id' => $role->id]);

    return $user->fresh();
}

/**
 * Crée un rôle local ordinaire dans une organisation.
 */
function organizationRole(Organization $organization, string $code = 'operateur'): Role
{
    return Role::create([
        'organization_id' => $organization->id,
        'code' => $code,
        'name' => ucfirst($code),
        'scope' => RoleScope::ORGANIZATION->value,
        'is_system' => false,
        'status' => 'active',
    ]);
}

/**
 * Donne à un membre exactement ces rôles.
 *
 * Le pivot `user_roles` porte un ULID sans valeur par défaut : `sync()` ne peut
 * donc pas l'écrire, et le reste du code passe déjà par le modèle.
 *
 * @param  array<int, Role>  $roles
 */
function giveRoles(string $organizationId, string $userId, array $roles): void
{
    $membership = OrganizationUser::where('organization_id', $organizationId)
        ->where('user_id', $userId)
        ->firstOrFail();

    UserRole::where('organization_user_id', $membership->id)->delete();

    foreach ($roles as $role) {
        UserRole::create(['organization_user_id' => $membership->id, 'role_id' => $role->id]);
    }
}

/**
 * Attache des permissions à un rôle, par leur code.
 *
 * Le pivot `role_permissions` porte un ULID sans valeur par défaut : `sync()`
 * ne peut donc pas l'écrire, et sept fichiers de test recopiaient déjà la même
 * boucle.
 *
 * @param  array<int, string>  $codes
 */
function givePermissions(Role $role, array $codes): void
{
    foreach (Permission::whereIn('code', $codes)->pluck('id') as $permissionId) {
        RolePermission::firstOrCreate(['role_id' => $role->id, 'permission_id' => $permissionId]);
    }
}

/**
 * Groupe de menu créé sur un rôle.
 *
 * Le code est tiré par le modèle, préfixé : il ne peut donc pas coïncider avec
 * un code du catalogue, présent ou futur.
 */
function menuGroup(string $roleId, array $overrides = []): RoleMenuGroup
{
    return RoleMenuGroup::create([
        'role_id' => $roleId,
        'code' => RoleMenuGroup::newCode(),
        'label' => 'Mon pôle',
        'icon' => 'Folder',
        'is_visible' => true,
        'position' => 15,
        ...$overrides,
    ]);
}

/**
 * Codes d'une réponse de menu, dans l'ordre où elle les rend.
 *
 * @param  array<int, array<string, mixed>>  $items
 * @return array<int, string>
 */
function menuCodesOf(array $items): array
{
    return array_column($items, 'code');
}

/**
 * Un logo d'essai, aux dimensions d'un vrai.
 *
 * `UploadedFile::fake()->image()` produit une image que GD reconnaît : la règle
 * `image` de la validation la fait passer, là où un fichier au seul bon nom
 * échouerait.
 */
function pngLogo(string $name = 'logo.png'): UploadedFile
{
    return UploadedFile::fake()->image($name, 200, 80);
}
