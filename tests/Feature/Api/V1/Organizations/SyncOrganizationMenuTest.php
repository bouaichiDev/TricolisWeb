<?php

use App\Modules\Organizations\Actions\SyncOrganizationMenu;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMenuItem;
use App\Shared\Enums\RoleScope;
use App\Shared\Menu\MenuCatalogue;

/**
 * Menu de base d'une organisation.
 *
 * Deux exigences tirent en sens contraire : l'administrateur doit **voir** le
 * menu de base dans son écran de réglage, ce qui suppose des lignes ; et une
 * entrée ajoutée à une phase suivante doit **parvenir** aux organisations déjà
 * créées, ce qu'un instantané figé empêcherait.
 *
 * La conciliation tient en une règle : créer les lignes manquantes, ne jamais
 * toucher aux existantes.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

function organizationEntryCount(): int
{
    return count(MenuCatalogue::forScope(RoleScope::ORGANIZATION));
}

it('gives a fresh organization the whole base menu', function (): void {
    $organization = Organization::factory()->create();

    $created = app(SyncOrganizationMenu::class)->execute($organization->id);

    expect($created)->toBe(organizationEntryCount())
        ->and(OrganizationMenuItem::where('organization_id', $organization->id)->count())
        ->toBe(organizationEntryCount());
});

it('creates every entry visible by default', function (): void {
    $organization = Organization::factory()->create();

    app(SyncOrganizationMenu::class)->execute($organization->id);

    expect(
        OrganizationMenuItem::where('organization_id', $organization->id)
            ->where('is_visible', false)
            ->exists()
    )->toBeFalse();
});

/**
 * Le cas qui compte pour les phases suivantes : une organisation créée avant
 * qu'une entrée n'existe la reçoit en rejouant la synchronisation.
 */
it('adds an entry that appeared after the organization was created', function (): void {
    $organization = Organization::factory()->create();
    app(SyncOrganizationMenu::class)->execute($organization->id);

    // Simule l'état d'avant une phase : l'entrée n'existait pas encore.
    OrganizationMenuItem::where('organization_id', $organization->id)
        ->where('code', 'audit')
        ->delete();

    $created = app(SyncOrganizationMenu::class)->execute($organization->id);

    expect($created)->toBe(1);
    $this->assertDatabaseHas('organization_menu_items', [
        'organization_id' => $organization->id,
        'code' => 'audit',
        'is_visible' => true,
    ]);
});

/**
 * Une organisation qui a masqué une entrée doit la retrouver masquée après la
 * synchronisation : sinon chaque phase réinitialiserait ses choix.
 */
it('leaves an existing choice untouched', function (): void {
    $organization = Organization::factory()->create();
    app(SyncOrganizationMenu::class)->execute($organization->id);

    OrganizationMenuItem::where('organization_id', $organization->id)
        ->where('code', 'agencies')
        ->update(['is_visible' => false, 'position' => 99]);

    app(SyncOrganizationMenu::class)->execute($organization->id);

    $this->assertDatabaseHas('organization_menu_items', [
        'organization_id' => $organization->id,
        'code' => 'agencies',
        'is_visible' => false,
        'position' => 99,
    ]);
});

it('is idempotent', function (): void {
    $organization = Organization::factory()->create();

    app(SyncOrganizationMenu::class)->execute($organization->id);
    $second = app(SyncOrganizationMenu::class)->execute($organization->id);

    expect($second)->toBe(0)
        ->and(OrganizationMenuItem::where('organization_id', $organization->id)->count())
        ->toBe(organizationEntryCount());
});

/**
 * Les entrées plateforme n'appartiennent à aucune organisation : leur donner
 * une ligne laisserait croire qu'un organisme peut les régler.
 */
it('never gives an organization a platform entry', function (): void {
    $organization = Organization::factory()->create();
    app(SyncOrganizationMenu::class)->execute($organization->id);

    $this->assertDatabaseMissing('organization_menu_items', [
        'organization_id' => $organization->id,
        'code' => 'organizations',
    ]);
});

describe('creation paths', function (): void {
    it('gives the base menu to an organization created through the API', function (): void {
        $response = $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
            ->postJson('/api/v1/organizations', ['code' => 'nouvelle', 'name' => 'Nouvelle'])
            ->assertCreated();

        expect(
            OrganizationMenuItem::where('organization_id', $response->json('data.id'))->count()
        )->toBe(organizationEntryCount());
    });

    it('gives the base menu to a self-registered transporter', function (): void {
        $response = $this->postJson('/api/v1/auth/register', [
            'firstName' => 'Sara',
            'lastName' => 'Amrani',
            'email' => 'sara@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization' => ['name' => 'Atlas Transport'],
        ])->assertCreated();

        expect(
            OrganizationMenuItem::where('organization_id', $response->json('data.organization.id'))->count()
        )->toBe(organizationEntryCount());
    });
});

describe('command', function (): void {
    it('brings every organization up to date', function (): void {
        Organization::factory()->count(2)->create();

        $this->artisan('tricolis:sync-organization-menus')->assertSuccessful();

        Organization::each(function (Organization $organization): void {
            expect(OrganizationMenuItem::where('organization_id', $organization->id)->count())
                ->toBe(organizationEntryCount());
        });
    });

    it('can be limited to a single organization by code', function (): void {
        $target = Organization::factory()->create(['code' => 'cible']);
        $other = Organization::factory()->create(['code' => 'autre']);

        $this->artisan('tricolis:sync-organization-menus', ['--organization' => 'cible'])
            ->assertSuccessful();

        expect(OrganizationMenuItem::where('organization_id', $target->id)->count())
            ->toBe(organizationEntryCount())
            ->and(OrganizationMenuItem::where('organization_id', $other->id)->count())
            ->toBe(0);
    });

    it('fails loudly when no organization matches', function (): void {
        $this->artisan('tricolis:sync-organization-menus', ['--organization' => 'inexistante'])
            ->assertFailed();
    });
});
