<?php

use App\Modules\Identity\Models\RoleMenuItem;

/**
 * Rattachement : sortir une entrée de son groupe, ou l'y faire entrer.
 *
 * Les deux sens sont le **même geste** — changer le parent — et il n'y a donc
 * qu'un réglage. Deux colonnes le portent pourtant, parce que « au premier
 * niveau » s'écrit `null` et serait sinon indistinguable de « je n'ai rien
 * choisi » : l'entrée retournerait dans son groupe au premier rechargement.
 *
 * Le nom et l'icône des entrées sont vérifiés par `RoleMenuNamingTest`.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    // Le menu se regle sur un role, et sur lui seul : chaque test en a donc un.
    $this->role = organizationRole($this->organization, 'exploitant');
    giveRoles($this->organization->id, $this->user->id, [$this->role]);
});

/**
 * Rattachement : sortir une entrée de son groupe, ou l'y faire entrer.
 *
 * Deux colonnes portent le choix, parce que « au premier niveau » s'écrit
 * `null` et serait sinon indistinguable de « je n'ai rien choisi ».
 */
describe('menu nesting', function (): void {
    it('promotes a child entry to the first level', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'parent' => null]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('role_menu_items', [
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'parent_overridden' => true,
            'parent_code' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(collect($response->json('data'))->firstWhere('code', 'agencies')['parent'])
            ->toBeNull();
    });

    it('nests a first-level entry into a group', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'customers', 'parent' => 'resources']],
            ])
            ->assertOk();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(collect($response->json('data'))->firstWhere('code', 'customers')['parent'])
            ->toBe('resources');
    });

    /**
     * Remettre une entrée là où le catalogue la place n'est pas un choix : la
     * ligne cesse de porter un rattachement, et l'entrée suivra le catalogue
     * si celui-ci la déplace un jour.
     */
    it('stops overriding when the catalogue parent is chosen again', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'parent' => null]],
            ])
            ->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'parent' => 'resources']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('role_menu_items', [
            'code' => 'agencies',
            'parent_overridden' => false,
        ]);
    });

    /**
     * La barre latérale rend deux niveaux. Un groupe rangé dans un groupe
     * placerait ses entrées au troisième, où rien ne les affiche : elles
     * disparaîtraient sans qu'aucune erreur ne soit levée.
     */
    it('refuses to nest a group inside another group', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'resources', 'parent' => 'operations']],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('role_menu_items', [
            'code' => 'resources',
            'parent_overridden' => true,
        ]);
    });

    it('rejects a parent that is not a group of the catalogue', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'parent' => 'customers']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.parent');
    });

    /**
     * Un groupe cible retiré du catalogue rend l'entrée à son groupe d'origine
     * plutôt qu'à la racine : une promotion que personne n'a demandée serait
     * plus surprenante qu'un retour au défaut.
     */
    it('falls back to the catalogue parent when the chosen group is gone', function (): void {
        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'is_visible' => true,
            'parent_overridden' => true,
            'parent_code' => 'groupe-disparu',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(collect($response->json('data'))->firstWhere('code', 'agencies')['parent'])
            ->toBe('resources');
    });
});
