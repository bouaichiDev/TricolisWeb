<?php

use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Budget de requêtes des listes du portail (Phase 8, performance) : le nombre
 * de requêtes SQL d'une page dépend des relations chargées, jamais du nombre
 * de lignes. Un N+1 ajouterait au moins une requête par ligne ajoutée.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->admin = CustomerPortal::headers('admin@ikea.test');
    $this->queries = function (string $uri): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withHeaders($this->admin)->getJson($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
});

/** Marge : une relation vide avant l'ajout peut coûter une requête de plus après. */
const QUERY_MARGIN = 3;

it('liste les commandes en un nombre de requêtes indépendant du volume', function (): void {
    $before = ($this->queries)('/api/v1/customer/orders?perPage=50');

    foreach (range(1, 12) as $index) {
        $this->withHeaders($this->admin)
            ->postJson('/api/v1/customer/orders', CustomerPortalOrders::payload(['customerReference' => "VOLUME-{$index}"]))
            ->assertCreated();
    }

    $after = ($this->queries)('/api/v1/customer/orders?perPage=50');

    expect($after)->toBeLessThanOrEqual($before + QUERY_MARGIN);
});

it('liste les articles du catalogue en un nombre de requêtes indépendant du volume', function (): void {
    $before = ($this->queries)('/api/v1/customer/catalog/items?perPage=50');
    $catalogId = ($this->withHeaders($this->admin)->getJson('/api/v1/customer/catalog/catalogs'))->json('data.0.id');

    foreach (range(1, 15) as $index) {
        $this->withHeaders($this->admin)
            ->postJson('/api/v1/customer/catalog/items', ['catalogId' => $catalogId, 'articleCode' => "VOL-{$index}", 'name' => "Article {$index}"])
            ->assertCreated();
    }

    $after = ($this->queries)('/api/v1/customer/catalog/items?perPage=50');

    expect($after)->toBeLessThanOrEqual($before + QUERY_MARGIN);
});
