<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Claims\Models\Claim;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Tours\Models\Tour;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->payload = fn (array $o = []): array => array_merge([
        'customerId' => $this->customer->id,
        'title' => 'Colis endommagé',
        'claimType' => 'damage',
        'status' => 'open',
    ], $o);
});

describe('claims creation', function (): void {
    it('creates a minimal claim, open and without resolution fields', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/claims', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Colis endommagé')
            ->assertJsonPath('data.closedAt', null)
            ->assertJsonPath('data.cost', null)
            ->assertJsonPath('data.decision', null);

        $this->assertDatabaseHas('claims', [
            'id' => $response->json('data.id'),
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);
    });

    it('accepts an optional order, service and tour', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $this->customer->id]);
        $service = OrderService::factory()->create(['order_id' => $order->id]);
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
        $tour = Tour::factory()->forAgency($agency)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/claims', ($this->payload)([
                'orderId' => $order->id,
                'orderServiceId' => $service->id,
                'tourId' => $tour->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.orderId', $order->id)
            ->assertJsonPath('data.tourId', $tour->id);
    });

    it('refuses a customer from another organization', function (): void {
        $foreign = Customer::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/claims', ($this->payload)(['customerId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('customerId');
    });

    it('refuses an order belonging to another customer', function (): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $order = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $otherCustomer->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/claims', ($this->payload)(['orderId' => $order->id]))
            ->assertStatus(422)->assertJsonValidationErrors('orderId');
    });

    it('refuses a service that does not belong to the given order', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $this->customer->id]);
        $otherOrder = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $this->customer->id]);
        $service = OrderService::factory()->create(['order_id' => $otherOrder->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/claims', ($this->payload)([
                'orderId' => $order->id,
                'orderServiceId' => $service->id,
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('orderServiceId');
    });

    it('accepts a service of another order of the same customer when no order is given', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $this->customer->id]);
        $service = OrderService::factory()->create(['order_id' => $order->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/claims', ($this->payload)(['orderServiceId' => $service->id]))
            ->assertCreated();
    });

    it('refuses a tour from another organization', function (): void {
        $foreignTour = Tour::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/claims', ($this->payload)(['tourId' => $foreignTour->id]))
            ->assertStatus(422)->assertJsonValidationErrors('tourId');
    });

    it('refuses a responsible user outside the organization', function (): void {
        $outsider = OrganizationUser::factory()->create()->user;

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/claims', ($this->payload)(['responsibleUserId' => $outsider->id]))
            ->assertStatus(422)->assertJsonValidationErrors('responsibleUserId');
    });

    it('creates through the customer route', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/claims", [
                'title' => 'Retard de livraison',
                'claimType' => 'delay',
                'status' => 'open',
            ])
            ->assertCreated()->assertJsonPath('data.customerId', $this->customer->id);
    });
});

describe('claims schema', function (): void {
    it('has neither claimNumber, severity nor legacyId', function (): void {
        $columns = Schema::getColumnListing('claims');

        expect($columns)->not->toContain('claim_number')
            ->and($columns)->not->toContain('severity')
            ->and($columns)->not->toContain('legacy_id')
            ->and($columns)->not->toContain('updated_at')
            ->and($columns)->not->toContain('resolution');

        expect(Schema::hasTable('claim_actions'))->toBeFalse()
            ->and(Schema::hasTable('claim_comments'))->toBeFalse()
            ->and(Schema::hasTable('claim_attachments'))->toBeFalse();
    });
});

describe('claims update and closure', function (): void {
    it('records the resolution fields', function (): void {
        $claim = Claim::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/claims/{$claim->id}", [
                'decision' => 'Remboursement partiel',
                'result' => 'accepted',
                'cost' => 240.50,
                'status' => 'resolved',
            ])
            ->assertOk()
            ->assertJsonPath('data.result', 'accepted')
            ->assertJsonPath('data.cost', '240.50');
    });

    it('refuses a negative cost', function (): void {
        $claim = Claim::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/claims/{$claim->id}", ['cost' => -1])
            ->assertStatus(422)->assertJsonValidationErrors('cost');
    });

    it('refuses a closing date before the creation date', function (): void {
        $claim = Claim::factory()->forCustomer($this->customer)->create(['created_at' => '2026-09-10 10:00:00']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/claims/{$claim->id}", ['closedAt' => '2026-09-01T10:00:00Z'])
            ->assertStatus(422)->assertJsonValidationErrors('closedAt');
    });

    it('audits the closure separately', function (): void {
        $claim = Claim::factory()->forCustomer($this->customer)->create(['created_at' => '2026-09-01 10:00:00']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/claims/{$claim->id}", ['closedAt' => '2026-09-05T10:00:00Z'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'claim.updated', 'entity_id' => $claim->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'claim.closed', 'entity_id' => $claim->id]);
    });

    it('records only the changed fields', function (): void {
        $claim = Claim::factory()->forCustomer($this->customer)->create(['title' => 'Avant']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/claims/{$claim->id}", ['title' => 'Après'])->assertOk();

        $log = AuditLog::where('action', 'claim.updated')->firstOrFail();
        expect($log->old_values)->toBe(['title' => 'Avant'])
            ->and($log->new_values)->toBe(['title' => 'Après']);
    });
});

describe('claims deletion', function (): void {
    it('deletes an open claim', function (): void {
        $claim = Claim::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/claims/{$claim->id}")->assertNoContent();

        $this->assertDatabaseMissing('claims', ['id' => $claim->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'claim.deleted', 'entity_id' => $claim->id]);
    });

    it('refuses to delete a closed claim', function (): void {
        $claim = Claim::factory()->forCustomer($this->customer)->closed()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/claims/{$claim->id}")->assertStatus(409);

        $this->assertDatabaseHas('claims', ['id' => $claim->id]);
    });
});

describe('claims scope and list', function (): void {
    it('hides a claim from another organization', function (): void {
        $foreign = Claim::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/claims/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/claims/{$foreign->id}", ['title' => 'Piraté'])->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/claims/{$foreign->id}")->assertNotFound();
    });

    it('lists only the claims of the active organization', function (): void {
        Claim::factory(2)->forCustomer($this->customer)->create();
        Claim::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/claims')->assertOk()->assertJsonCount(2, 'data');
    });

    it('searches across the instruction fields', function (): void {
        Claim::factory()->forCustomer($this->customer)->create(['cause' => 'Emballage insuffisant']);
        Claim::factory()->forCustomer($this->customer)->create(['decision' => 'Geste commercial']);
        Claim::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/claims?search=Emballage')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/claims?search=commercial')->assertOk()->assertJsonCount(1, 'data');
    });

    it('filters, sorts and paginates', function (): void {
        Claim::factory()->forCustomer($this->customer)->create(['claim_type' => 'delay', 'status' => 'closed']);
        Claim::factory(4)->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/claims?claimType=delay')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/claims?status=closed')->assertOk()->assertJsonCount(1, 'data');

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/claims?perPage=2')->assertOk()->assertJsonCount(2, 'data');
        expect($response->json('meta.perPage'))->toBe(2);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/claims?sort=organization_id')->assertStatus(422);
    });

    it('lists through the customer, order and tour routes', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $this->customer->id]);
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
        $tour = Tour::factory()->forAgency($agency)->create();

        Claim::factory()->forCustomer($this->customer)->create(['order_id' => $order->id, 'tour_id' => $tour->id]);
        Claim::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$this->customer->id}/claims")->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$order->id}/claims")->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$tour->id}/claims")->assertOk()->assertJsonCount(1, 'data');
    });
});
