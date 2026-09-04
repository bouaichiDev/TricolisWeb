<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleDashboardConfiguration;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;

/**
 * Le tableau de bord servi.
 *
 * Ce fichier tient la règle qui compte, et une seule :
 *
 * ```
 * widget visible = activé par un rôle  ET  permission effective présente
 * ```
 *
 * Le reste en découle. Le réglage lui-même est vérifié par
 * `Identity/RoleDashboardTest.php` ; ici on vérifie qu'un réglage ne suffit
 * jamais.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    // Un membre ordinaire, et non le propriétaire semé : `is_owner` court-circuite
    // toute vérification de permission, et l'ensemble de ce fichier porte
    // précisément sur ce que les permissions retirent. Le propriétaire a son
    // propre cas, plus bas.
    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);
    $this->user = $this->membership->user;
});

function configure(Role $role, array $keys): void
{
    RoleDashboardConfiguration::updateOrCreate(
        ['role_id' => $role->id],
        ['widgets' => array_values(array_map(
            static fn (string $key, int $index): array => ['key' => $key, 'position' => $index + 1],
            $keys,
            array_keys($keys),
        ))],
    );
}

function dashboardKeys(): array
{
    return array_column(
        test()->actingAs(test()->user, 'sanctum')->withHeaders(test()->headers)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->json('data.widgets'),
        'key'
    );
}

describe('access', function (): void {
    it('refuses a member without dashboard.view', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard')
            ->assertForbidden();
    });

    it('requires the organization header', function (): void {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/dashboard')->assertForbidden();
    });
});

describe('one role', function (): void {
    it('serves exactly what the role configured, and nothing else', function (): void {
        $role = organizationRole($this->organization, 'exploitant');
        givePermissions($role, ['dashboard.view', 'orders.view', 'tours.view']);
        giveRoles($this->organization->id, $this->user->id, [$role]);
        configure($role, ['orders_today', 'tours_today']);

        expect(dashboardKeys())->toBe(['orders_today', 'tours_today']);
    });

    /**
     * Le §21 en toutes lettres : l'absence de configuration vaut « les défauts
     * du catalogue », une configuration vide vaut « aucun widget ». Les
     * confondre rendrait le second inatteignable.
     */
    it('falls back to the catalogue defaults when nothing is configured', function (): void {
        $role = organizationRole($this->organization, 'bureau');
        givePermissions($role, ['dashboard.view', 'customers.view']);
        giveRoles($this->organization->id, $this->user->id, [$role]);

        expect(dashboardKeys())->toBe(['customers_count']);
    });

    it('serves nothing when the role configured nothing on purpose', function (): void {
        $role = organizationRole($this->organization, 'bureau');
        givePermissions($role, ['dashboard.view', 'customers.view']);
        giveRoles($this->organization->id, $this->user->id, [$role]);
        RoleDashboardConfiguration::create(['role_id' => $role->id, 'widgets' => []]);

        expect(dashboardKeys())->toBe([]);
    });
});

describe('several roles', function (): void {
    beforeEach(function (): void {
        $this->planner = organizationRole($this->organization, 'planner');
        givePermissions($this->planner, ['dashboard.view', 'tours.view', 'orders.view']);
        configure($this->planner, ['tours_today', 'orders_to_plan']);

        $this->office = organizationRole($this->organization, 'bureau');
        givePermissions($this->office, ['dashboard.view', 'claims.view', 'orders.view']);
        configure($this->office, ['open_claims', 'orders_to_plan']);
    });

    /**
     * Union, jamais intersection : ajouter un rôle ne doit pas retirer un
     * widget. C'est déjà la règle des permissions et celle du menu.
     */
    it('joins what each role shows', function (): void {
        giveRoles($this->organization->id, $this->user->id, [$this->planner, $this->office]);

        expect(dashboardKeys())->toEqualCanonicalizing(['tours_today', 'orders_to_plan', 'open_claims']);
    });

    it('shows a widget both roles carry only once', function (): void {
        giveRoles($this->organization->id, $this->user->id, [$this->planner, $this->office]);

        expect(array_count_values(dashboardKeys())['orders_to_plan'])->toBe(1);
    });

    /**
     * Le rang retenu est le **plus petit configuré**, et la clé départage les
     * égalités. Sans ce second critère, l'ordre dépendrait de celui où SQL rend
     * les rôles — c'est-à-dire d'un appel à l'autre.
     */
    it('orders on the smallest configured position, then on the key', function (): void {
        giveRoles($this->organization->id, $this->user->id, [$this->planner, $this->office]);

        // `orders_to_plan` est second chez le planificateur, second chez le
        // bureau : il reste second. `open_claims` est premier chez le bureau.
        expect(dashboardKeys())->toBe(['open_claims', 'tours_today', 'orders_to_plan']);
    });
});

describe('permissions decide, configuration only proposes', function (): void {
    beforeEach(function (): void {
        $this->role = organizationRole($this->organization, 'bureau');
        givePermissions($this->role, ['dashboard.view', 'customers.view']);
        giveRoles($this->organization->id, $this->user->id, [$this->role]);
        configure($this->role, ['customers_count']);
    });

    it('serves the widget while the permission is there', function (): void {
        expect(dashboardKeys())->toBe(['customers_count']);
    });

    /**
     * Le §54 : retirer la permission fait disparaître le widget **sans qu'on
     * touche à la configuration**. Les deux réglages restent indépendants, et
     * c'est le second qui protège.
     */
    it('drops it as soon as the permission is taken away', function (): void {
        RolePermission::where('role_id', $this->role->id)
            ->whereIn('permission_id', Permission::where('code', 'customers.view')->pluck('id'))
            ->delete();

        expect(dashboardKeys())->toBe([]);

        // La configuration, elle, n'a pas bougé : la permission revenue, le
        // widget revient sans qu'on ait à le rerégler.
        expect(RoleDashboardConfiguration::where('role_id', $this->role->id)->first()->widgets)
            ->toBe([['key' => 'customers_count', 'position' => 1]]);
    });

    /**
     * Le §26 : pas de valeur dans le JSON pour un widget refusé. Le masquer
     * côté interface tout en transportant son chiffre serait une fuite
     * complète — il suffirait d'ouvrir l'onglet réseau.
     */
    it('never carries the value of a widget it refuses', function (): void {
        Customer::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        RolePermission::where('role_id', $this->role->id)
            ->whereIn('permission_id', Permission::where('code', 'customers.view')->pluck('id'))
            ->delete();

        $body = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard')->assertOk()->getContent();

        expect($body)->not->toContain('customers_count');
    });
});

describe('organization isolation', function (): void {
    /**
     * Le §55 : un compte qui travaille dans deux organisations reçoit le
     * tableau de bord de celle qu'il a activée, et rien de l'autre.
     */
    it('serves the widgets of the active organization only', function (): void {
        $planner = organizationRole($this->organization, 'planner');
        givePermissions($planner, ['dashboard.view', 'tours.view']);
        giveRoles($this->organization->id, $this->user->id, [$planner]);
        configure($planner, ['tours_today']);

        $other = Organization::factory()->create();
        $elsewhereMembership = OrganizationUser::create([
            'organization_id' => $other->id,
            'user_id' => $this->user->id,
            'is_owner' => false,
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $office = organizationRole($other, 'bureau');
        givePermissions($office, ['dashboard.view', 'claims.view']);
        UserRole::create([
            'organization_user_id' => $elsewhereMembership->id,
            'role_id' => $office->id,
        ]);
        configure($office, ['open_claims']);

        expect(dashboardKeys())->toBe(['tours_today']);

        $elsewhere = array_column(
            $this->actingAs($this->user, 'sanctum')
                ->withHeaders(['X-Organization-Id' => $other->id])
                ->getJson('/api/v1/dashboard')->assertOk()->json('data.widgets'),
            'key'
        );

        expect($elsewhere)->toBe(['open_claims']);
    });
});

describe('the values themselves', function (): void {
    /**
     * Le compte est celui de l'organisation active, pas celui de la table.
     * Les clients d'un autre organisme sont créés exprès : sans le filtre, ils
     * gonfleraient le chiffre sans que rien ne le signale.
     */
    it('counts what the organization actually holds, and nobody else', function (): void {
        $before = Customer::where('organization_id', $this->organization->id)->count();
        Customer::factory()->count(4)->create(['organization_id' => $this->organization->id]);
        Customer::factory()->count(7)->create(['organization_id' => Organization::factory()->create()->id]);

        $role = organizationRole($this->organization, 'bureau');
        givePermissions($role, ['dashboard.view', 'customers.view']);
        giveRoles($this->organization->id, $this->user->id, [$role]);
        configure($role, ['customers_count']);

        $widget = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard')->assertOk()->json('data.widgets.0');

        expect($widget['data']['value'])->toBe($before + 4)
            ->and($widget['type'])->toBe('kpi');
    });

    /**
     * Le propriétaire d'un organisme détient tout sans passer par un rôle, et
     * peut n'en porter aucun. Lui rendre un écran vide aurait ressemblé à une
     * panne : l'absence de rôle vaut « rien de configuré », donc les défauts.
     */
    it('serves the catalogue defaults to an owner who carries no role', function (): void {
        $owner = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => true]);

        $keys = array_column(
            $this->actingAs($owner->user, 'sanctum')->withHeaders($this->headers)
                ->getJson('/api/v1/dashboard')->assertOk()->json('data.widgets'),
            'key'
        );

        expect($keys)->toBe(['customers_count', 'agencies_count', 'users_count', 'roles_count']);
    });

    it('names the active organization', function (): void {
        $role = organizationRole($this->organization, 'bureau');
        givePermissions($role, ['dashboard.view']);
        giveRoles($this->organization->id, $this->user->id, [$role]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.organization.id', $this->organization->id);
    });
});
