<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Providers\Models\Provider;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->provider = Provider::factory()->forOrganization($this->organization)->create();

    $this->line = fn (array $o = []): array => array_merge([
        'description' => 'Course sous-traitée',
        'quantity' => 1,
        'unitCost' => 80,
    ], $o);

    $this->payload = fn (array $o = [], ?array $lines = null): array => array_merge([
        'providerId' => $this->provider->id,
        'settlementNumber' => 'STL-2026-0001',
        'status' => 'draft',
        'lines' => $lines ?? [($this->line)()],
    ], $o);
});

describe('settlements creation', function (): void {
    it('creates a settlement with one line', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.settlementNumber', 'STL-2026-0001')
            ->assertJsonCount(1, 'data.lines');

        $this->assertDatabaseHas('provider_settlements', [
            'id' => $response->json('data.id'),
            'organization_id' => $this->organization->id,
        ]);
    });

    it('refuses a settlement without any line', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(['lines' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('lines');
    });

    it('refuses a provider from another organization', function (): void {
        $foreign = Provider::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(['providerId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('providerId');
    });

    it('refuses an inverted period and a duplicated number', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)([
                'periodFrom' => '2026-09-30', 'periodTo' => '2026-09-01',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('periodTo');

        ProviderSettlement::factory()->forProvider($this->provider)->create(['settlement_number' => 'STL-DUP']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(['settlementNumber' => 'STL-DUP']))
            ->assertStatus(422)->assertJsonValidationErrors('settlementNumber');
    });

    it('creates through the provider route', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/providers/{$this->provider->id}/settlements", [
                'settlementNumber' => 'STL-VIA-PROVIDER',
                'status' => 'draft',
                'lines' => [($this->line)()],
            ])
            ->assertCreated()->assertJsonPath('data.providerId', $this->provider->id);
    });
});

describe('settlements totals', function (): void {
    it('computes the subtotal from the lines and adds the submitted tax', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(
                ['taxTotal' => 30],
                [
                    ($this->line)(['quantity' => 2, 'unitCost' => 80]),
                    ($this->line)(['quantity' => 1, 'unitCost' => 40]),
                ],
            ))
            ->assertCreated();

        expect($response->json('data.subtotal'))->toBe('200.00')
            ->and($response->json('data.taxTotal'))->toBe('30.00')
            ->and($response->json('data.total'))->toBe('230.00');
    });

    it('recomputes the total when the tax is changed', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/provider-settlements/$id", ['taxTotal' => 16])
            ->assertOk()->assertJsonPath('data.total', '96.00');
    });

    it('computes the line total as quantity times unit cost', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(lines: [
                ($this->line)(['quantity' => 2.5, 'unitCost' => 33.33]),
            ]))
            ->assertCreated();

        expect($response->json('data.lines.0.totalCost'))->toBe('83.33');
    });
});

describe('settlement lines', function (): void {
    it('refuses to settle the same service twice', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create();
        $service = OrderService::factory()->create(['order_id' => $order->id]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(lines: [
                ($this->line)(['orderServiceId' => $service->id]),
            ]))->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/provider-settlements/{$response->json('data.id')}/lines",
                ($this->line)(['orderServiceId' => $service->id]))
            ->assertStatus(422)->assertJsonValidationErrors('orderServiceId');
    });

    it('refuses a service planned for another provider', function (): void {
        $otherProvider = Provider::factory()->forOrganization($this->organization)->create();
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
        $tour = Tour::factory()->forAgency($agency)->create(['provider_id' => $otherProvider->id]);
        $stop = TourStop::factory()->forTour($tour)->create();

        $order = Order::factory()->forOrganization($this->organization)->create();
        $service = OrderService::factory()->create(['order_id' => $order->id]);
        TourStopService::factory()->forStop($stop)->create(['order_service_id' => $service->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(lines: [
                ($this->line)(['orderServiceId' => $service->id]),
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('lines.0.orderServiceId');
    });

    it('accepts a service planned for the settled provider', function (): void {
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
        $tour = Tour::factory()->forAgency($agency)->create(['provider_id' => $this->provider->id]);
        $stop = TourStop::factory()->forTour($tour)->create();

        $order = Order::factory()->forOrganization($this->organization)->create();
        $service = OrderService::factory()->create(['order_id' => $order->id]);
        TourStopService::factory()->forStop($stop)->create(['order_service_id' => $service->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(lines: [
                ($this->line)(['orderServiceId' => $service->id]),
            ]))
            ->assertCreated();
    });

    it('allows a service to be both billed and settled', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create();
        $service = OrderService::factory()->create(['order_id' => $order->id]);

        InvoiceLine::factory()->create(['order_service_id' => $service->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(lines: [
                ($this->line)(['orderServiceId' => $service->id]),
            ]))
            ->assertCreated();
    });

    it('refuses to remove the last line', function (): void {
        $settlement = ProviderSettlement::factory()->forProvider($this->provider)->create();
        $only = ProviderSettlementLine::factory()->forSettlement($settlement)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/provider-settlements/{$settlement->id}/lines/{$only->id}")
            ->assertStatus(409);
    });

    it('recalculates the settlement when a line changes', function (): void {
        $settlement = ProviderSettlement::factory()->forProvider($this->provider)->create();
        $line = ProviderSettlementLine::factory()->forSettlement($settlement)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/provider-settlements/{$settlement->id}/lines/{$line->id}", ['quantity' => 4])
            ->assertOk()->assertJsonPath('data.totalCost', '320.00');

        expect($settlement->fresh()->subtotal)->toBe('320.00');
    });

    it('refuses a negative quantity or unit cost', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(lines: [($this->line)(['quantity' => -1])]))
            ->assertStatus(422)->assertJsonValidationErrors('lines.0.quantity');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)(lines: [($this->line)(['unitCost' => -1])]))
            ->assertStatus(422)->assertJsonValidationErrors('lines.0.unitCost');
    });
});

describe('settlements schema, scope and audit', function (): void {
    it('has no invented columns', function (): void {
        $lines = Schema::getColumnListing('provider_settlement_lines');

        expect($lines)->not->toContain('tax_rate')
            ->and($lines)->not->toContain('tax_amount')
            ->and($lines)->not->toContain('status')
            ->and($lines)->not->toContain('service_date')
            ->and(Schema::getColumnListing('provider_settlements'))->not->toContain('created_at');
    });

    it('hides a settlement from another organization', function (): void {
        $foreign = ProviderSettlement::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/provider-settlements/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/provider-settlements/{$foreign->id}")->assertNotFound();
    });

    it('lists, searches, filters and sorts', function (): void {
        ProviderSettlement::factory()->forProvider($this->provider)->create(['settlement_number' => 'ZZZ-1', 'status' => 'paid']);
        ProviderSettlement::factory(3)->forProvider($this->provider)->create();
        ProviderSettlement::factory(2)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/provider-settlements')->assertOk()->assertJsonCount(4, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/provider-settlements?search=ZZZ')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/provider-settlements?status=paid')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/providers/{$this->provider->id}/settlements")->assertOk()->assertJsonCount(4, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/provider-settlements?sort=organization_id')->assertStatus(422);
    });

    it('audits creation, update, recalculation and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'provider_settlement.created', 'entity_type' => 'provider_settlement', 'entity_id' => $id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/provider-settlements/$id/lines", ($this->line)())->assertCreated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'provider_settlement_line.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'provider_settlement_totals.recalculated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/provider-settlements/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'provider_settlement.deleted', 'entity_id' => $id]);
        $this->assertDatabaseMissing('provider_settlement_lines', ['settlement_id' => $id]);
    });
});
