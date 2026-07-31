<?php

use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Customers\Models\Customer;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::where('organization_id', $this->organization->id)->firstOrFail();
});

describe('customer catalogs', function (): void {
    it('lists the catalogs of a customer', function (): void {
        CustomerCatalog::factory(2)->forCustomer($this->customer)->create();
        CustomerCatalog::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$this->customer->id}/catalogs")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('creates a catalog', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/catalogs", ['code' => 'CAT-01', 'name' => 'Catalogue principal'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'CAT-01');

        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'customer_catalog']);
    });

    it('refuses a duplicated code for the same customer', function (): void {
        CustomerCatalog::factory()->forCustomer($this->customer)->create(['code' => 'CAT-01']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/catalogs", ['code' => 'CAT-01', 'name' => 'Doublon'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    });

    it('allows the same code for two different customers', function (): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        CustomerCatalog::factory()->forCustomer($other)->create(['code' => 'CAT-01']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/catalogs", ['code' => 'CAT-01', 'name' => 'Autre client'])
            ->assertCreated();
    });

    it('updates and deletes a catalog', function (): void {
        $catalog = CustomerCatalog::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/customers/{$this->customer->id}/catalogs/{$catalog->id}", ['name' => 'Renommé'])
            ->assertOk()->assertJsonPath('data.name', 'Renommé');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/customers/{$this->customer->id}/catalogs/{$catalog->id}")
            ->assertNoContent();
    });

    it('hides a catalog belonging to another customer', function (): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $catalog = CustomerCatalog::factory()->forCustomer($other)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$this->customer->id}/catalogs/{$catalog->id}")
            ->assertNotFound();
    });

    it('hides a customer from another organization', function (): void {
        $foreign = Customer::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$foreign->id}/catalogs")
            ->assertNotFound();
    });
});

describe('catalog items', function (): void {
    beforeEach(function (): void {
        $this->catalog = CustomerCatalog::factory()->forCustomer($this->customer)->create();
    });

    it('adds an item to a catalog', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/catalogs/{$this->catalog->id}/items", [
                'articleCode' => 'ART-1',
                'name' => 'Chaise',
                'weight' => 4.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.articleCode', 'ART-1');
    });

    it('refuses a duplicated article code in the same catalog', function (): void {
        CustomerCatalogItem::factory()->forCatalog($this->catalog)->create(['article_code' => 'ART-1']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/catalogs/{$this->catalog->id}/items", [
                'articleCode' => 'ART-1',
                'name' => 'Doublon',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('articleCode');
    });

    it('refuses a negative weight', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/catalogs/{$this->catalog->id}/items", [
                'articleCode' => 'ART-2',
                'name' => 'Poids négatif',
                'weight' => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('weight');
    });

    it('hides an item belonging to another catalog', function (): void {
        $otherCatalog = CustomerCatalog::factory()->forCustomer($this->customer)->create();
        $item = CustomerCatalogItem::factory()->forCatalog($otherCatalog)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$this->customer->id}/catalogs/{$this->catalog->id}/items/{$item->id}")
            ->assertNotFound();
    });
});
