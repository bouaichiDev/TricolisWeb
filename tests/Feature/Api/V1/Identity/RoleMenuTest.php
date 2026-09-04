<?php

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleMenuItem;

/**
 * Réglage du menu d'un rôle.
 *
 * **C'est le seul endroit où le menu se règle.** Chaque rôle porte le sien en
 * entier : ce qu'il voit, dans quel ordre, sous quel nom, quelle icône et dans
 * quel groupe. Il se réglait auparavant à deux niveaux, et il fallait savoir
 * lequel ouvrir pour obtenir quoi.
 *
 * Ce fichier tient la **visibilité** et l'enregistrement. Le nom et l'icône
 * sont vérifiés par `RoleMenuNamingTest`, le rattachement par
 * `RoleMenuNestingTest`, les groupes créés par `RoleMenuGroupTest`, et le cumul
 * de rôles par `RoleMenuResolutionTest`.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->role = organizationRole($this->organization, 'exploitant');
    giveRoles($this->organization->id, $this->user->id, [$this->role]);
});

describe('role menu configuration', function (): void {
    it('lists the catalogue with the state this role has chosen', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/roles/{$this->role->id}/menu")
            ->assertOk();

        $agencies = collect($response->json('data'))->firstWhere('code', 'agencies');

        expect($agencies['isVisible'])->toBeTrue();
    });

    /**
     * Le réglage d'un rôle ne déborde pas sur un autre : chacun porte le sien.
     * C'est la contrepartie de la liberté donnée — deux rôles peuvent nommer et
     * ranger la même entrée différemment.
     */
    it('keeps one role setting out of another', function (): void {
        $other = organizationRole($this->organization, 'comptable');

        RoleMenuItem::create([
            'role_id' => $other->id,
            'code' => 'agencies',
            'label' => 'Sites',
            'is_visible' => false,
        ]);

        $agencies = collect(
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->getJson("/api/v1/roles/{$this->role->id}/menu")
                ->assertOk()
                ->json('data')
        )->firstWhere('code', 'agencies');

        expect($agencies['label'])->toBeNull()
            ->and($agencies['isVisible'])->toBeTrue();
    });

    /**
     * Une entrée masquée reste **proposée** dans l'écran de réglage, décochée :
     * la retirer de la liste rendrait le geste irréversible, faute d'endroit où
     * la remontrer.
     */
    it('still offers an entry the role has hidden', function (): void {
        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'is_visible' => false,
        ]);

        $agencies = collect(
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->getJson("/api/v1/roles/{$this->role->id}/menu")
                ->assertOk()
                ->json('data')
        )->firstWhere('code', 'agencies');

        expect($agencies)->not->toBeNull()
            ->and($agencies['isVisible'])->toBeFalse();
    });

    it('stores what the role hides', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'isVisible' => false]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('role_menu_items', [
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'is_visible' => false,
        ]);
    });

    /**
     * **Un rôle ordinaire peut masquer l'administration.** Elle était verrouillée
     * du temps où le réglage valait pour l'organisation entière — la masquer
     * l'aurait retirée au propriétaire lui-même. Un rôle « Bureau » qui n'en a
     * que faire doit, lui, pouvoir la ranger.
     */
    it('lets an ordinary role hide the administration', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'administration', 'isVisible' => false]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('role_menu_items', [
            'role_id' => $this->role->id,
            'code' => 'administration',
            'is_visible' => false,
        ]);
    });

    /**
     * **Le menu du rôle système se règle**, alors que ses permissions ne se
     * modifient pas. `update` protège le jeu de permissions — un rôle système
     * les porte toutes — mais le menu ne porte rien de tel : il range des
     * écrans, il n'en ouvre aucun. L'interdire privait l'administrateur du seul
     * menu qu'il voit lui-même.
     */
    it('lets the system role menu be set, though the role itself cannot', function (): void {
        $admin = Role::where('organization_id', $this->organization->id)
            ->where('is_system', true)
            ->firstOrFail();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$admin->id}/menu", [
                'items' => [['code' => 'agencies', 'isVisible' => false]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('role_menu_items', [
            'role_id' => $admin->id,
            'code' => 'agencies',
            'is_visible' => false,
        ]);
    });

    /**
     * Une seule entrée reste verrouillée, et pour tous les rôles : « Mon
     * organisation » garde à chacun un pied dans l'administration.
     */
    it('locks only my-organization, whatever the role', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/roles/{$this->role->id}/menu")
            ->assertOk();

        $locked = collect($response->json('data'))
            ->reject(fn (array $item): bool => $item['canHide'])
            ->pluck('code')
            ->all();

        expect($locked)->toBe(['my-organization']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'my-organization', 'isVisible' => false]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('role_menu_items', [
            'code' => 'my-organization',
            'is_visible' => true,
        ]);
    });

    it('rejects a code that is not in the catalogue', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'inexistant', 'isVisible' => false]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.code');
    });

    it('records the change in the audit trail', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'isVisible' => false]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'role_menu_updated',
        ]);
    });
});
