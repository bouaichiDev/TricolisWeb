<?php

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleDashboardConfiguration;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Testing\TestResponse;

/**
 * Réglage du tableau de bord d'un rôle.
 *
 * Ce fichier tient l'**écriture** : ce qu'on accepte d'enregistrer, ce qu'on
 * refuse, et qui a le droit de le faire. Ce que l'utilisateur en voit ensuite
 * est vérifié par `Dashboard/DashboardTest.php` — les deux ne se recouvrent
 * pas, et c'est le point : une configuration enregistrée n'est pas une
 * permission accordée.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->role = organizationRole($this->organization, 'exploitant');
    givePermissions($this->role, ['orders.view', 'tours.view', 'dashboard.view', 'dashboard.configure']);
    giveRoles($this->organization->id, $this->user->id, [$this->role]);
});

function putDashboard(array $widgets): TestResponse
{
    return test()->actingAs(test()->user, 'sanctum')->withHeaders(test()->headers)
        ->putJson('/api/v1/roles/'.test()->role->id.'/dashboard', ['widgets' => $widgets]);
}

describe('catalogue', function (): void {
    it('lists every widget, with the state this role has chosen', function (): void {
        $items = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/roles/{$this->role->id}/dashboard")
            ->assertOk()
            ->json('data');

        expect(collect($items)->firstWhere('key', 'orders_today'))->not->toBeNull();
    });

    /**
     * Le widget que le rôle ne peut pas voir **reste proposé**, désactivé. Le
     * masquer aurait laissé croire qu'il n'existe pas, alors qu'il ne manque
     * qu'une permission — et l'écran qui l'accorde est à côté.
     */
    it('still offers a widget the role lacks the permission for, and says so', function (): void {
        $items = collect(
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->getJson("/api/v1/roles/{$this->role->id}/dashboard")
                ->assertOk()
                ->json('data')
        );

        $invoices = $items->firstWhere('key', 'draft_invoices');

        expect($invoices['availableForRole'])->toBeFalse()
            ->and($invoices['requiredPermission'])->toBe('invoices.view');
    });

    it('names no resolver, no query and no component', function (): void {
        $first = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard/widgets')
            ->assertOk()
            ->json('data.0');

        expect(array_keys($first))->not->toContain('resolver', 'query', 'component', 'sql');
    });
});

describe('writing a configuration', function (): void {
    it('creates the configuration on the first save', function (): void {
        putDashboard([['key' => 'orders_today', 'position' => 1]])->assertOk();

        expect(RoleDashboardConfiguration::where('role_id', $this->role->id)->first()->widgets)
            ->toBe([['key' => 'orders_today', 'position' => 1]]);
    });

    it('replaces the selection rather than adding to it', function (): void {
        putDashboard([['key' => 'orders_today', 'position' => 1]])->assertOk();
        putDashboard([['key' => 'tours_today', 'position' => 1]])->assertOk();

        $enabled = collect(putDashboard([['key' => 'tours_today', 'position' => 1]])->json('data'))
            ->where('isEnabled', true)
            ->pluck('key');

        expect($enabled->all())->toBe(['tours_today']);
    });

    /**
     * Un tableau vide n'est pas une absence de choix : c'est le choix de ne
     * rien voir. C'est toute la raison d'être d'une table de configuration
     * plutôt que d'une pivot.
     */
    it('accepts an empty selection, and keeps it', function (): void {
        putDashboard([])->assertOk();

        $configuration = RoleDashboardConfiguration::where('role_id', $this->role->id)->first();

        expect($configuration)->not->toBeNull()
            ->and($configuration->widgets)->toBe([]);
    });

    it('keeps only the key and the position', function (): void {
        putDashboard([[
            'key' => 'orders_today',
            'position' => 2,
            'resolver' => 'App\\Evil',
            'label' => 'Ce que je veux',
        ]])->assertOk();

        $stored = RoleDashboardConfiguration::where('role_id', $this->role->id)->first()->widgets;

        expect(array_keys($stored[0]))->toBe(['key', 'position']);
    });
});

describe('what a configuration may not contain', function (): void {
    it('rejects a widget the catalogue does not know', function (): void {
        putDashboard([['key' => 'chiffre_daffaires_secret', 'position' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('widgets.0.key');
    });

    it('rejects the same widget twice', function (): void {
        putDashboard([
            ['key' => 'orders_today', 'position' => 1],
            ['key' => 'orders_today', 'position' => 2],
        ])->assertStatus(422)->assertJsonValidationErrors('widgets.1.key');
    });

    it('rejects a negative position', function (): void {
        putDashboard([['key' => 'orders_today', 'position' => -1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('widgets.0.position');
    });

    /**
     * Accepter ce widget aurait laissé croire qu'il s'affichera. Il ne
     * s'affichera pas : l'intersection avec les permissions a lieu à chaque
     * chargement. Le refus le dit tout de suite, là où l'on peut y remédier.
     */
    it('refuses a widget whose permission the role does not hold', function (): void {
        putDashboard([['key' => 'draft_invoices', 'position' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('widgets.0.key');
    });
});

describe('resetting', function (): void {
    it('deletes the row rather than emptying it', function (): void {
        putDashboard([['key' => 'orders_today', 'position' => 1]])->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/roles/{$this->role->id}/dashboard")
            ->assertOk();

        expect(RoleDashboardConfiguration::where('role_id', $this->role->id)->exists())->toBeFalse();
    });

    it('brings the catalogue defaults back', function (): void {
        givePermissions($this->role, ['customers.view']);
        putDashboard([])->assertOk();

        $enabled = collect(
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->deleteJson("/api/v1/roles/{$this->role->id}/dashboard")
                ->json('data')
        )->where('isEnabled', true)->pluck('key');

        expect($enabled)->toContain('customers_count');
    });
});

describe('who may configure', function (): void {
    it('refuses a member without dashboard.configure', function (): void {
        $membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);

        $this->actingAs($membership->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/roles/{$this->role->id}/dashboard")
            ->assertForbidden();
    });

    /**
     * Un rôle d'une autre organisation se présente comme **absent**, pas comme
     * interdit : un 403 confirmerait que cet identifiant existe ailleurs, et
     * c'est la différence entre les deux réponses qui constitue la fuite.
     */
    it('hides a role from another organization', function (): void {
        $other = Organization::factory()->create();
        $foreign = organizationRole($other, 'etranger');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/roles/{$foreign->id}/dashboard")
            ->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->putJson("/api/v1/roles/{$foreign->id}/dashboard", ['widgets' => []])
            ->assertNotFound();
    });

    /**
     * Le rôle système fait exception, comme pour le menu : `admin` porte toutes
     * les permissions, et lui interdire de régler son tableau de bord aurait
     * privé l'administrateur du seul qu'il voit.
     */
    it('lets the system role dashboard be set, though the role itself cannot', function (): void {
        $admin = Role::where('organization_id', $this->organization->id)
            ->where('code', 'admin')
            ->firstOrFail();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->putJson("/api/v1/roles/{$admin->id}/dashboard", [
                'widgets' => [['key' => 'orders_today', 'position' => 1]],
            ])->assertOk();
    });
});

describe('audit', function (): void {
    it('records who changed a role dashboard', function (): void {
        putDashboard([['key' => 'orders_today', 'position' => 1]])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'role_dashboard_updated',
            'entity_id' => $this->role->id,
        ]);
    });

    it('records a reset', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/roles/{$this->role->id}/dashboard")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'role_dashboard_reset',
        ]);
    });
});
