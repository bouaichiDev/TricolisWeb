<?php

/**
 * Ce qu'un groupe créé fait dans le menu.
 *
 * Il se comporte **exactement comme un groupe livré** : il se remplit, se
 * renomme, se réordonne et se masque. C'est tout l'intérêt de le faire
 * rejoindre le catalogue au moment du calcul plutôt que de le tenir dans une
 * liste à part — le reste du code n'a qu'une sorte d'entrée à connaître.
 *
 * Sa création et sa suppression sont vérifiées par `RoleMenuGroupTest`.
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

describe('a created group in the effective menu', function (): void {
    /**
     * Un groupe vide afficherait un titre qui n'ouvre rien. Il reste en
     * revanche dans l'écran de réglage : c'est là qu'on le remplit, et un
     * groupe qui disparaîtrait aussitôt créé serait impossible à utiliser.
     */
    it('stays out of the sidebar while it is empty', function (): void {
        $group = menuGroup($this->role->id);

        $effective = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')->assertOk();

        $catalogue = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/roles/{$this->role->id}/menu")->assertOk();

        expect(menuCodesOf($effective->json('data')))->not->toContain($group->code)
            ->and(menuCodesOf($catalogue->json('data')))->toContain($group->code);
    });

    it('appears once an entry is moved into it', function (): void {
        $group = menuGroup($this->role->id);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'parent' => $group->code]],
            ])
            ->assertOk();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        $items = collect($response->json('data'));

        expect(menuCodesOf($items->all()))->toContain($group->code)
            ->and($items->firstWhere('code', 'agencies')['parent'])->toBe($group->code);
    });

    it('carries its own name and icon', function (): void {
        $group = menuGroup($this->role->id, ['label' => 'Pôle Nord', 'icon' => 'MapPin']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'parent' => $group->code]],
            ])
            ->assertOk();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        $item = collect($response->json('data'))->firstWhere('code', $group->code);

        expect($item['label'])->toBe('Pôle Nord')
            ->and($item['icon'])->toBe('MapPin');
    });

    /** Le réglage passe par le même enregistrement que le reste du menu. */
    it('is renamed and reordered through the ordinary save', function (): void {
        $group = menuGroup($this->role->id);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [[
                    'code' => $group->code,
                    'label' => 'Pôle Sud',
                    'icon' => 'Flag',
                    'position' => 3,
                    'isVisible' => false,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('role_menu_groups', [
            'code' => $group->code,
            'label' => 'Pôle Sud',
            'icon' => 'Flag',
            'position' => 3,
            'is_visible' => false,
        ]);
    });

    /**
     * Un groupe créé n'a pas de libellé livré vers lequel revenir : vider son
     * nom laisserait un titre vide, introuvable dans la barre latérale.
     */
    it('keeps its name when the field arrives empty', function (): void {
        $group = menuGroup($this->role->id, ['label' => 'Mon pôle']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => $group->code, 'label' => '  ']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('role_menu_groups', [
            'code' => $group->code,
            'label' => 'Mon pôle',
        ]);
    });
});
