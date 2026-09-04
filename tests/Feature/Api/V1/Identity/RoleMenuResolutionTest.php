<?php

use App\Modules\Identity\Models\RoleMenuItem;

/**
 * Ce que les rôles d'un utilisateur lui laissent voir.
 *
 * **La règle est l'union : une entrée s'affiche dès qu'un seul de ses rôles la
 * montre.** C'est déjà celle des permissions, et les deux mécanismes doivent
 * s'accorder — un menu plus restrictif que les droits proposerait moins que ce
 * que l'utilisateur peut ouvrir, sans qu'il ait moyen de s'en apercevoir.
 *
 * L'intersection avait le défaut inverse, plus grave : **ajouter** un rôle à
 * quelqu'un lui aurait retiré des écrans.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->role = organizationRole($this->organization, 'exploitant');
    giveRoles($this->organization->id, $this->user->id, [$this->role]);
});

describe('role menu applied to the effective menu', function (): void {
    it('hides an entry the only role of the user hides', function (): void {
        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'is_visible' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(menuCodesOf($response->json('data')))->not->toContain('agencies');
    });

    /**
     * **L'union est la règle.** Ajouter un rôle à quelqu'un ne doit jamais lui
     * retirer un écran : une promotion qui restreint est un comportement que
     * personne ne prévoit.
     */
    it('keeps an entry a second role still shows', function (): void {
        $other = organizationRole($this->organization, 'comptable');

        giveRoles($this->organization->id, $this->user->id, [$this->role, $other]);

        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'is_visible' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(menuCodesOf($response->json('data')))->toContain('agencies');
    });

    /**
     * **La présentation, elle, ne se fond pas.** Deux rôles peuvent nommer et
     * ranger la même entrée autrement ; il faut alors choisir, et c'est le rôle
     * dont le code vient en premier qui l'emporte. Le tri par code rend ce
     * départage stable et lisible — l'administrateur peut en changer en
     * renommant.
     */
    it('takes the naming from the role whose code comes first', function (): void {
        $first = organizationRole($this->organization, 'aaa-premier');
        $last = organizationRole($this->organization, 'zzz-dernier');

        giveRoles($this->organization->id, $this->user->id, [$last, $first]);

        RoleMenuItem::create([
            'role_id' => $first->id,
            'code' => 'agencies',
            'label' => 'Sites',
            'is_visible' => true,
        ]);

        RoleMenuItem::create([
            'role_id' => $last->id,
            'code' => 'agencies',
            'label' => 'Antennes',
            'is_visible' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(collect($response->json('data'))->firstWhere('code', 'agencies')['label'])
            ->toBe('Sites');
    });

    /** Le réglage d'un rôle ne déborde pas sur les porteurs d'un autre. */
    it('leaves a user of another role untouched', function (): void {
        $other = organizationRole($this->organization, 'comptable');

        giveRoles($this->organization->id, $this->user->id, [$other]);

        RoleMenuItem::create([
            'role_id' => $this->role->id,
            'code' => 'agencies',
            'is_visible' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(menuCodesOf($response->json('data')))->toContain('agencies');
    });
});
