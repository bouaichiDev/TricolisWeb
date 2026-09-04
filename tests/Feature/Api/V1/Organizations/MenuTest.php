<?php

use App\Modules\Identity\Models\RoleMenuItem;

/**
 * Menu servi à l'utilisateur connecté.
 *
 * Ce fichier ne tient que `GET /menu` — ce que l'appelant reçoit. Le
 * **réglage** vit entièrement sur le rôle, et se vérifie sous `Identity` :
 * `RoleMenuTest` pour la visibilité, `RoleMenuNamingTest` pour les noms,
 * `RoleMenuNestingTest` pour le rattachement, `RoleMenuGroupTest` pour les
 * groupes créés, `RoleMenuResolutionTest` pour le cumul de rôles.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    // Le menu se règle sur un rôle, et sur lui seul : chaque test en a donc un.
    $this->role = organizationRole($this->organization, 'exploitant');
    giveRoles($this->organization->id, $this->user->id, [$this->role]);
});

it('returns the organization menu to a member', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/menu')
        ->assertOk();

    expect(menuCodesOf($response->json('data')))->toContain('customers', 'agencies', 'users');
});

/**
 * Un compte plateforme reçoit le menu plateforme, pas le menu d'organisme
 * expurgé : clients et agences appartiennent aux organismes. N'ayant pas de
 * rôle d'organisation, il reçoit le catalogue tel qu'il est livré.
 */
it('returns the platform menu to a platform administrator', function (): void {
    $response = $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
        ->getJson('/api/v1/menu')
        ->assertOk();

    $codes = menuCodesOf($response->json('data'));

    expect($codes)->toContain('organizations')
        ->and($codes)->not->toContain('customers')
        ->and($codes)->not->toContain('dashboard');
});

it('hides an entry the role has disabled', function (): void {
    RoleMenuItem::create([
        'role_id' => $this->role->id,
        'code' => 'agencies',
        'is_visible' => false,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/menu')
        ->assertOk();

    $codes = menuCodesOf($response->json('data'));

    expect($codes)->not->toContain('agencies')
        ->and($codes)->toContain('depots');
});

/**
 * Le réglage vaut pour un rôle et un seul : le masquer sur l'un ne doit rien
 * changer pour qui porte l'autre.
 */
it('keeps the setting inside its role', function (): void {
    $other = organizationRole($this->organization, 'comptable');

    RoleMenuItem::create([
        'role_id' => $other->id,
        'code' => 'agencies',
        'is_visible' => false,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/menu')
        ->assertOk();

    expect(menuCodesOf($response->json('data')))->toContain('agencies');
});

it('follows the position chosen by the role', function (): void {
    RoleMenuItem::create([
        'role_id' => $this->role->id,
        'code' => 'customers',
        'is_visible' => true,
        'position' => 999,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/menu')
        ->assertOk();

    $codes = menuCodesOf($response->json('data'));

    expect(array_search('customers', $codes, true))->toBe(count($codes) - 1);
});

/**
 * Un groupe dont plus aucun enfant ne subsiste afficherait un titre vide.
 */
it('drops a group left without any visible child', function (): void {
    // Tous les enfants du groupe, pas seulement les deux premiers : depuis la
    // Phase 4, « Ressources » porte aussi fournisseurs, chauffeurs et
    // véhicules, et en laisser un visible garderait le groupe.
    foreach (['agencies', 'depots', 'providers', 'drivers', 'vehicles'] as $code) {
        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => $code,
            'is_visible' => false,
        ]);
    }

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/menu')
        ->assertOk();

    expect(menuCodesOf($response->json('data')))->not->toContain('resources');
});
