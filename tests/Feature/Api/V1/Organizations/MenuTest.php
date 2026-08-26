<?php

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMenuItem;

/**
 * Menu de navigation.
 *
 * Le catalogue vit en code — route, icône et clé i18n y sont couplées au
 * frontend. Ce qu'une organisation choisit, c'est **quelles entrées elle voit
 * et dans quel ordre**, et cela vit en base.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

function menuCodes(array $items): array
{
    return array_column($items, 'code');
}

describe('effective menu', function (): void {
    it('returns the organization menu to a member', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(menuCodes($response->json('data')))->toContain('customers', 'agencies', 'users');
    });

    /**
     * Un compte plateforme reçoit le menu plateforme, pas le menu d'organisme
     * expurgé : clients et agences appartiennent aux organismes.
     */
    it('returns the platform menu to a platform administrator', function (): void {
        $response = $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
            ->getJson('/api/v1/menu')
            ->assertOk();

        $codes = menuCodes($response->json('data'));

        expect($codes)->toContain('organizations')
            ->and($codes)->not->toContain('customers')
            ->and($codes)->not->toContain('dashboard');
    });

    it('hides an entry the organization has disabled', function (): void {
        OrganizationMenuItem::create([
            'organization_id' => $this->organization->id,
            'code' => 'agencies',
            'is_visible' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        $codes = menuCodes($response->json('data'));

        expect($codes)->not->toContain('agencies')
            ->and($codes)->toContain('depots');
    });

    /**
     * Le réglage vaut pour une organisation et une seule : le masquer chez
     * l'une ne doit rien changer chez l'autre.
     */
    it('keeps the setting inside its organization', function (): void {
        $other = Organization::factory()->create();

        OrganizationMenuItem::create([
            'organization_id' => $other->id,
            'code' => 'agencies',
            'is_visible' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(menuCodes($response->json('data')))->toContain('agencies');
    });

    it('follows the position chosen by the organization', function (): void {
        OrganizationMenuItem::create([
            'organization_id' => $this->organization->id,
            'code' => 'customers',
            'is_visible' => true,
            'position' => 999,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        $codes = menuCodes($response->json('data'));

        expect(array_search('customers', $codes, true))->toBe(count($codes) - 1);
    });

    /**
     * Un groupe dont plus aucun enfant ne subsiste afficherait un titre vide.
     */
    it('drops a group left without any visible child', function (): void {
        // Tous les enfants du groupe, pas seulement les deux premiers : depuis
        // la Phase 4, « Ressources » porte aussi fournisseurs, chauffeurs et
        // vehicules, et en laisser un visible garderait le groupe.
        foreach (['agencies', 'depots', 'providers', 'drivers', 'vehicles'] as $code) {
            OrganizationMenuItem::create([
                'organization_id' => $this->organization->id,
                'code' => $code,
                'is_visible' => false,
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu')
            ->assertOk();

        expect(menuCodes($response->json('data')))->not->toContain('resources');
    });
});

describe('menu configuration', function (): void {
    it('lists the catalogue with its chosen state', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/menu/catalogue')
            ->assertOk();

        $agencies = collect($response->json('data'))->firstWhere('code', 'agencies');

        expect($agencies['isVisible'])->toBeTrue()
            ->and($agencies['canHide'])->toBeTrue();
    });

    it('stores visibility and position', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson('/api/v1/menu', [
                'items' => [['code' => 'agencies', 'isVisible' => false, 'position' => 5]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('organization_menu_items', [
            'organization_id' => $this->organization->id,
            'code' => 'agencies',
            'is_visible' => false,
            'position' => 5,
        ]);
    });

    /**
     * L'administration ne se masque pas : un organisme qui la retirerait
     * n'aurait plus d'écran pour revenir en arrière. La demande est ignorée,
     * pas refusée — la requête reste valide, c'est la contrainte qui l'emporte.
     */
    it('refuses to hide an entry the organization must keep', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson('/api/v1/menu', [
                'items' => [['code' => 'administration', 'isVisible' => false]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('organization_menu_items', [
            'code' => 'administration',
            'is_visible' => true,
        ]);
    });

    it('rejects a code that is not in the catalogue', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson('/api/v1/menu', [
                'items' => [['code' => 'inexistant', 'isVisible' => false]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.code');
    });

    it('records the change in the audit trail', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson('/api/v1/menu', [
                'items' => [['code' => 'agencies', 'isVisible' => false]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'menu_updated',
        ]);
    });
});
