<?php

use App\Modules\Identity\Models\Permission;
use App\Shared\Enums\MenuSection;
use Database\Seeders\PermissionMenuMap;
use Database\Seeders\PermissionSeeder;

/**
 * Section de menu des permissions.
 *
 * `module` est une découpe technique — 48 valeurs, dont `tour_stop_services`.
 * Grouper le formulaire de rôle dessus produisait 48 sections. La section est
 * la découpe métier correspondante, portée par la permission plutôt que par le
 * module : les deux ne coïncident pas toujours.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

it('assigns a section to every permission', function (): void {
    expect(Permission::whereNull('menu_section')->pluck('code')->all())->toBe([]);
});

it('assigns only known sections', function (): void {
    $used = Permission::distinct()->pluck('menu_section')->all();

    expect(array_diff($used, MenuSection::values()))->toBe([]);
});

/**
 * Un module absent de la table retomberait silencieusement dans
 * « Administration » — une permission de facturation classée avec les rôles,
 * sans que rien ne le signale. Ce test rend l'oubli visible.
 */
it('maps every module explicitly, with no silent fallback', function (): void {
    $modules = Permission::distinct()->pluck('module')->all();

    expect(array_diff($modules, PermissionMenuMap::knownModules()))->toBe([]);
});

/**
 * Créer ou supprimer une organisation dépasse le périmètre d'un organisme.
 * Les isoler évite de les présenter à côté de « Modifier mon organisation »,
 * dont elles n'ont ni la portée ni les conséquences.
 */
it('separates platform permissions from the organization ones of the same module', function (): void {
    expect(Permission::where('code', 'organizations.create')->value('menu_section'))
        ->toBe(MenuSection::PLATFORM->value)
        ->and(Permission::where('code', 'organizations.delete')->value('menu_section'))
        ->toBe(MenuSection::PLATFORM->value)
        ->and(Permission::where('code', 'organizations.view')->value('menu_section'))
        ->toBe(MenuSection::ADMINISTRATION->value);
});

it('exposes the section through the API', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/permissions')
        ->assertOk();

    $customers = collect($response->json('data'))->firstWhere('code', 'customers.view');

    expect($customers['menuSection'])->toBe(MenuSection::CUSTOMERS->value);
});

/**
 * Le seeder est rejouable, et la section doit suivre.
 *
 * `firstOrCreate` ne touchait pas une ligne existante : sur une base déjà
 * semée, la colonne serait restée vide et le formulaire n'aurait rien eu pour
 * grouper. Reclasser une permission doit aussi pouvoir se rejouer.
 */
it('reapplies the section on an already seeded database', function (): void {
    Permission::where('code', 'customers.view')->update(['menu_section' => null]);

    $this->seed(PermissionSeeder::class);

    expect(Permission::where('code', 'customers.view')->value('menu_section'))
        ->toBe(MenuSection::CUSTOMERS->value);
});

it('keeps the sections few enough to be usable', function (): void {
    $sections = Permission::distinct()->count('menu_section');
    $modules = Permission::distinct()->count('module');

    expect($sections)->toBeLessThanOrEqual(12)
        ->and($modules)->toBeGreaterThan($sections);
});
