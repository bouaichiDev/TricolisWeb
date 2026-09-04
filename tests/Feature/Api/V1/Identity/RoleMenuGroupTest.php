<?php

use App\Modules\Identity\Models\RoleMenuGroup;
use App\Modules\Identity\Models\RoleMenuItem;
use App\Shared\Menu\MenuCatalogue;

/**
 * Naissance et mort d'un groupe de menu créé par un rôle.
 *
 * **Pourquoi ceux-là naissent en base alors que le catalogue reste en code :**
 * un groupe n'ouvre rien. Ni route, ni permission — c'est un titre repliable
 * au-dessus d'entrées qui gardent, elles, leur destination du code. Les deux
 * défauts qui gardent le catalogue hors de la base — une route menant à « Page
 * introuvable », une permission ouvrant un écran interdit — ne peuvent pas se
 * produire ici.
 *
 * Ce qu'un groupe créé *fait* une fois né est vérifié par
 * `RoleMenuGroupBehaviourTest`.
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

describe('creating a group', function (): void {
    it('creates a group from a name and an icon', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/roles/{$this->role->id}/menu/groups", ['label' => 'Mon pôle', 'icon' => 'Folder'])
            ->assertOk();

        $group = collect($response->json('data'))->firstWhere('label', 'Mon pôle');

        expect($group)->not->toBeNull()
            ->and($group['route'])->toBeNull()
            ->and($group['permission'])->toBeNull()
            ->and($group['isCustom'])->toBeTrue()
            // Un groupe n'a pas de niveau où descendre : la barre latérale en
            // rend deux, et ses entrées se retrouveraient au troisième.
            ->and($group['canReparent'])->toBeFalse();
    });

    /**
     * Le code est tiré par le serveur, opaque et préfixé : le laisser saisir
     * permettrait de heurter un code du catalogue, et l'on réglerait une entrée
     * en croyant en régler une autre.
     */
    it('names the group itself, outside the catalogue namespace', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/roles/{$this->role->id}/menu/groups", ['label' => 'Mon pôle', 'icon' => 'Folder'])
            ->assertOk();

        $code = RoleMenuGroup::where('role_id', $this->role->id)
            ->value('code');

        expect($code)->toStartWith(RoleMenuGroup::PREFIX)
            ->and(MenuCatalogue::find($code))->toBeNull();
    });

    it('rejects an icon the frontend cannot render', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/roles/{$this->role->id}/menu/groups", ['label' => 'Mon pôle', 'icon' => 'PasUneIcone'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('icon');
    });

    it('rejects a group without a name', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/roles/{$this->role->id}/menu/groups", ['label' => '', 'icon' => 'Folder'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('label');
    });

    it('records the creation in the audit trail', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/roles/{$this->role->id}/menu/groups", ['label' => 'Mon pôle', 'icon' => 'Folder'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'role_menu_group_created',
        ]);
    });
});

describe('deleting a group', function (): void {
    /**
     * Les entrées ne partent pas avec le groupe : les supprimer retirerait des
     * écrans pour un geste de rangement.
     */
    it('returns its entries to the catalogue parent', function (): void {
        $group = menuGroup($this->role->id);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/roles/{$this->role->id}/menu", [
                'items' => [['code' => 'agencies', 'parent' => $group->code]],
            ])
            ->assertOk();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/roles/{$this->role->id}/menu/groups/{$group->code}")
            ->assertOk();

        $agencies = collect($response->json('data'))->firstWhere('code', 'agencies');

        expect(menuCodesOf($response->json('data')))->not->toContain($group->code)
            ->and($agencies['parent'])->toBe('resources');

        $this->assertDatabaseHas('role_menu_items', [
            'code' => 'agencies',
            'parent_overridden' => false,
        ]);
    });

    /**
     * Les entrées qui pointaient vers lui ne restent pas suspendues : leur
     * rattachement redevient celui du catalogue.
     */
    it('clears the parent of the entries it held', function (): void {
        $group = menuGroup($this->role->id);

        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'is_visible' => true,
            'parent_overridden' => true,
            'parent_code' => $group->code,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/roles/{$this->role->id}/menu/groups/{$group->code}")
            ->assertOk();

        $this->assertDatabaseMissing('role_menu_items', ['parent_code' => $group->code]);
    });

    /** Un groupe livré n'appartient pas au rôle : il le masque, il ne le supprime pas. */
    it('refuses to delete a catalogue group', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/roles/{$this->role->id}/menu/groups/resources")
            ->assertNotFound();
    });

    /**
     * Chaque rôle porte ses propres groupes : supprimer par le mauvais rôle ne
     * doit pas atteindre celui d'à côté.
     */
    it('never reaches a group of another role', function (): void {
        $other = organizationRole($this->organization, 'comptable');
        $group = menuGroup($other->id, ['label' => 'Ailleurs']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/roles/{$this->role->id}/menu/groups/{$group->code}")
            ->assertNotFound();

        $this->assertDatabaseHas('role_menu_groups', ['code' => $group->code]);
    });
});
