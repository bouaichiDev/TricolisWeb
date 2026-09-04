<?php

use App\Modules\Identity\Models\RoleMenuItem;

/**
 * Nom et icône des entrées.
 *
 * Ces trois choix viennent se poser **par-dessus** le catalogue, sans le
 * remplacer : un rôle qui n'a rien renommé suit les traductions
 * livrées, y compris celles à venir. C'est ce qui rend le geste réversible.
 *
 * Ni l'un ni l'autre ne peut fabriquer un menu cassé — au pire une entrée mal
 * nommée, que le même écran corrige. La route et la permission, elles, restent
 * hors de portée.
 *
 * Le rattachement est vérifié par `RoleMenuNestingTest`.
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
 * Nom et icône choisis par le rôle.
 *
 * Ils viennent se poser **par-dessus** le catalogue, sans le remplacer : une
 * organisation qui n'a rien renommé suit les traductions livrées, y compris
 * celles à venir. C'est ce qui rend le geste réversible.
 */
describe('menu naming', function (): void {
    it('stores a label and an icon chosen by the organization', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'label' => 'Sites', 'icon' => 'MapPin']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('role_menu_items', [
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'label' => 'Sites',
            'icon' => 'MapPin',
        ]);
    });

    it('serves the chosen label and icon in the effective menu', function (): void {
        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'label' => 'Sites',
            'icon' => 'MapPin',
            'is_visible' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        $agencies = collect($response->json('data'))->firstWhere('code', 'agencies');

        expect($agencies['label'])->toBe('Sites')
            ->and($agencies['icon'])->toBe('MapPin')
            // La clé reste : elle est le défaut vers lequel un retour est possible.
            ->and($agencies['labelKey'])->toBe('nav.agencies');
    });

    /**
     * Le champ vidé **est** le bouton « revenir au défaut ». Enregistrer la
     * chaîne vide afficherait une entrée sans nom dans la barre latérale.
     */
    it('returns to the catalogue label when the field is emptied', function (): void {
        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'label' => 'Sites',
            'is_visible' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'label' => '  ']],
            ])
            ->assertOk();

        expect(collect($response->json('data'))->firstWhere('code', 'agencies')['label'])->toBeNull();
    });

    /**
     * Absent de la requête n'est pas la même chose qu'envoyé vide : un client
     * qui n'enverrait que les rangs effacerait sinon tous les libellés choisis.
     */
    it('leaves an untouched label alone', function (): void {
        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'label' => 'Sites',
            'is_visible' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'position' => 3]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('role_menu_items', [
            'code' => 'agencies',
            'label' => 'Sites',
            'position' => 3,
        ]);
    });

    /**
     * Une icône est un composant React. Accepter un nom que `menuIcons.ts`
     * ignore le ferait retomber sur l'icône neutre, et l'administrateur
     * croirait avoir choisi.
     */
    it('rejects an icon the frontend cannot render', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'icon' => 'PasUneIcone']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.icon');
    });

    it('rejects a label longer than the column holds', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'label' => str_repeat('a', 61)]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.label');
    });

    /**
     * Une icône retirée de `menuIcons.ts` ne doit pas laisser l'entrée sur
     * l'icône neutre : elle retrouve celle du catalogue.
     */
    it('falls back to the catalogue icon when the stored one is unknown', function (): void {
        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'icon' => 'IconeDisparue',
            'is_visible' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(collect($response->json('data'))->firstWhere('code', 'agencies')['icon'])
            ->not->toBe('IconeDisparue');
    });
});
