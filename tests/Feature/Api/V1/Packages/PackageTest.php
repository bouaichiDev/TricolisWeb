<?php

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Packages\Models\Package;
use App\Modules\Packages\Models\PackageType;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->order = Order::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);
});

describe('packages', function (): void {
    it('creates a package', function (): void {
        $type = PackageType::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages", [
                'packageTypeId' => $type->id,
                'barcode' => 'PKG-0001',
                'weight' => 12.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.barcode', 'PKG-0001');

        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'package']);
    });

    it('refuses a duplicated barcode', function (): void {
        Package::factory()->forOrder($this->order)->create(['barcode' => 'PKG-0001']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages", ['barcode' => 'PKG-0001'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');
    });

    it('refuses a negative weight', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages", ['weight' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('weight');
    });

    it('refuses a package type from another organization', function (): void {
        $foreignType = PackageType::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages", ['packageTypeId' => $foreignType->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('packageTypeId');
    });

    it('refuses a parent from another order', function (): void {
        $otherOrder = Order::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);
        $foreignParent = Package::factory()->forOrder($otherOrder)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages", ['parentPackageId' => $foreignParent->id])
            ->assertNotFound();
    });

    it('refuses a cycle in the hierarchy', function (): void {
        $parent = Package::factory()->forOrder($this->order)->create();
        $child = Package::factory()->childOf($parent)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/orders/{$this->order->id}/packages/{$parent->id}", ['parentPackageId' => $child->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parentPackageId');
    });

    it('refuses a package being its own parent', function (): void {
        $package = Package::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/orders/{$this->order->id}/packages/{$package->id}", ['parentPackageId' => $package->id])
            ->assertStatus(422);
    });

    it('returns the package tree', function (): void {
        $parent = Package::factory()->forOrder($this->order)->create();
        Package::factory(2)->childOf($parent)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$this->order->id}/packages/tree")
            ->assertOk();

        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.children'))->toHaveCount(2);
    });

    it('refuses deleting a package that contains children', function (): void {
        $parent = Package::factory()->forOrder($this->order)->create();
        Package::factory()->childOf($parent)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/orders/{$this->order->id}/packages/{$parent->id}")
            ->assertStatus(409);
    });

    it('hides a package from an order of another organization', function (): void {
        $foreignOrder = Order::factory()->create();
        $package = Package::factory()->forOrder($foreignOrder)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$foreignOrder->id}/packages/{$package->id}")
            ->assertNotFound();
    });
});

describe('package lines', function (): void {
    beforeEach(function (): void {
        $this->package = Package::factory()->forOrder($this->order)->create();
        $this->line = OrderLine::factory()->forOrder($this->order)->create(['quantity' => 10]);
    });

    it('assigns a line to a package', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages/{$this->package->id}/lines", [
                'orderLineId' => $this->line->id,
                'quantity' => 4,
            ])
            ->assertCreated()
            ->assertJsonPath('data.quantity', '4.000');
    });

    it('refuses assigning more than the ordered quantity', function (): void {
        $second = Package::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages/{$this->package->id}/lines", [
                'orderLineId' => $this->line->id, 'quantity' => 7,
            ])->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages/{$second->id}/lines", [
                'orderLineId' => $this->line->id, 'quantity' => 4,
            ])->assertStatus(422)->assertJsonValidationErrors('quantity');
    });

    it('splits a line across several packages within the ordered quantity', function (): void {
        $second = Package::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages/{$this->package->id}/lines", [
                'orderLineId' => $this->line->id, 'quantity' => 6,
            ])->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages/{$second->id}/lines", [
                'orderLineId' => $this->line->id, 'quantity' => 4,
            ])->assertCreated();

        expect($this->line->fresh()->assignedQuantity())->toBe(10.0);
    });

    it('refuses a line from another order', function (): void {
        $otherOrder = Order::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);
        $foreignLine = OrderLine::factory()->forOrder($otherOrder)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages/{$this->package->id}/lines", [
                'orderLineId' => $foreignLine->id, 'quantity' => 1,
            ])->assertNotFound();
    });

    it('releases an assignment', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/packages/{$this->package->id}/lines", [
                'orderLineId' => $this->line->id, 'quantity' => 3,
            ])->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/orders/{$this->order->id}/packages/{$this->package->id}/lines/{$response->json('data.id')}")
            ->assertNoContent();

        expect($this->line->fresh()->assignedQuantity())->toBe(0.0);
    });
});
