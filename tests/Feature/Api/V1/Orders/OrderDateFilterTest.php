<?php

use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\OrganizationUser;

/**
 * Les bornes de date de la liste des commandes, et le jour qu'elles couvrent.
 *
 * Ce filtre a pris son importance avec le forage du tableau de bord : cliquer
 * une colonne de « Commandes par jour » ouvre la liste avec les deux bornes sur
 * ce jour-là. Or `order_date` est un `dateTime` : comparé tel quel,
 * `createdTo=2026-09-03` valait « jusqu'au 3 à minuit » et rendait une liste
 * **vide** sous une colonne qui en comptait trente. Le pire des cas — la carte
 * a l'air juste, la liste a l'air vide, et rien ne relie les deux.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);
    $this->user = $membership->user;

    $role = organizationRole($this->organization, 'exploitant');
    givePermissions($role, ['orders.view']);
    giveRoles($this->organization->id, $this->user->id, [$role]);

    // Une commande en plein après-midi : c'est le cas que la borne haute
    // écartait, et celui de presque toutes les commandes réelles.
    Order::factory()->forOrganization($this->organization)->create(['order_date' => '2026-09-03 14:30:00']);
    Order::factory()->forOrganization($this->organization)->create(['order_date' => '2026-09-04 09:00:00']);
});

it('covers the whole day named by the upper bound', function (): void {
    $response = test()->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/orders?createdFrom=2026-09-03&createdTo=2026-09-03')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
});

it('keeps both bounds inclusive over a range', function (): void {
    $response = test()->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/orders?createdFrom=2026-09-03&createdTo=2026-09-04')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2);
});

it('excludes what falls outside', function (): void {
    $response = test()->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/orders?createdFrom=2026-09-04&createdTo=2026-09-04')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
});
