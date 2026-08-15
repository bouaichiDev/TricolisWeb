<?php

use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->order = Order::factory()->forOrganization($this->organization)->create();

    $this->payload = fn (array $o = []): array => array_merge([
        'orderId' => $this->order->id,
        'recipientName' => 'Karim Bensaïd',
        'deliveredAt' => '2026-09-01T14:30:00Z',
    ], $o);
});

describe('proofs of delivery creation', function (): void {
    it('creates a minimal proof without any document', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.recipientName', 'Karim Bensaïd')
            ->assertJsonPath('data.signatureDocumentId', null)
            ->assertJsonPath('data.photoDocumentId', null);

        $this->assertDatabaseHas('proofs_of_delivery', [
            'id' => $response->json('data.id'),
            'order_id' => $this->order->id,
            'created_by' => $this->user->id,
        ]);
    });

    it('creates a proof with a signature and a photo', function (): void {
        $signature = Document::factory()->create(['organization_id' => $this->organization->id]);
        $photo = Document::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', ($this->payload)([
                'signatureDocumentId' => $signature->id,
                'photoDocumentId' => $photo->id,
                'remark' => 'Colis remis en main propre',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.signatureDocumentId', $signature->id)
            ->assertJsonPath('data.photoDocumentId', $photo->id);
    });

    it('accepts an optional service and stop', function (): void {
        $service = OrderService::factory()->create(['order_id' => $this->order->id]);
        $stop = TourStop::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', ($this->payload)(['orderServiceId' => $service->id]))
            ->assertCreated()->assertJsonPath('data.orderServiceId', $service->id);

        expect($stop)->not->toBeNull();
    });

    it('refuses an order from another organization', function (): void {
        $foreign = Order::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', ($this->payload)(['orderId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('orderId');
    });

    it('refuses a service belonging to another order', function (): void {
        $otherOrder = Order::factory()->forOrganization($this->organization)->create();
        $service = OrderService::factory()->create(['order_id' => $otherOrder->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', ($this->payload)(['orderServiceId' => $service->id]))
            ->assertStatus(422)->assertJsonValidationErrors('orderServiceId');
    });

    it('refuses a signature document from another organization', function (): void {
        $foreignDocument = Document::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', ($this->payload)(['signatureDocumentId' => $foreignDocument->id]))
            ->assertStatus(422)->assertJsonValidationErrors('signatureDocumentId');
    });

    it('refuses a photo document from another organization', function (): void {
        $foreignDocument = Document::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', ($this->payload)(['photoDocumentId' => $foreignDocument->id]))
            ->assertStatus(422)->assertJsonValidationErrors('photoDocumentId');
    });

    it('accepts the same document as signature and photo', function (): void {
        $document = Document::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', ($this->payload)([
                'signatureDocumentId' => $document->id,
                'photoDocumentId' => $document->id,
            ]))
            ->assertCreated();
    });

    it('requires a recipient name and a delivery date', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', ['orderId' => $this->order->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipientName', 'deliveredAt']);
    });
});

describe('proofs of delivery schema', function (): void {
    it('stores no file path', function (): void {
        $columns = Schema::getColumnListing('proofs_of_delivery');

        expect($columns)->not->toContain('signature_path')
            ->and($columns)->not->toContain('photo_path')
            ->and($columns)->not->toContain('status');

        expect(Schema::hasTable('signatures'))->toBeFalse()
            ->and(Schema::hasTable('delivery_photos'))->toBeFalse();
    });
});

describe('proofs of delivery immutability', function (): void {
    it('exposes no PATCH nor DELETE route', function (): void {
        $proof = ProofOfDelivery::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/proofs-of-delivery/{$proof->id}", ['recipientName' => 'X'])
            ->assertStatus(405);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/proofs-of-delivery/{$proof->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('proofs_of_delivery', ['id' => $proof->id]);
    });
});

describe('proofs of delivery read', function (): void {
    it('lists only the proofs of the active organization', function (): void {
        ProofOfDelivery::factory(2)->forOrder($this->order)->create();
        ProofOfDelivery::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/proofs-of-delivery')->assertOk()->assertJsonCount(2, 'data');
    });

    it('hides a proof from another organization', function (): void {
        $foreign = ProofOfDelivery::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/proofs-of-delivery/{$foreign->id}")->assertNotFound();
    });

    it('lists and creates through the order route', function (): void {
        ProofOfDelivery::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$this->order->id}/proofs-of-delivery")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/proofs-of-delivery", [
                'recipientName' => 'Yassine',
                'deliveredAt' => '2026-09-02T10:00:00Z',
            ])
            ->assertCreated()->assertJsonPath('data.orderId', $this->order->id);
    });

    it('filters by recipient and delivery date', function (): void {
        ProofOfDelivery::factory()->forOrder($this->order)->create([
            'recipient_name' => 'Zineb Alaoui',
            'delivered_at' => '2026-09-10 10:00:00',
        ]);
        ProofOfDelivery::factory()->forOrder($this->order)->create(['delivered_at' => '2026-10-10 10:00:00']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/proofs-of-delivery?search=Zineb')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/proofs-of-delivery?deliveredTo=2026-09-30T00:00:00Z')
            ->assertOk()->assertJsonCount(1, 'data');
    });
});

describe('proofs of delivery audit', function (): void {
    it('audits creation', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', ($this->payload)())->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'proof_of_delivery.created',
            'entity_type' => 'proof_of_delivery',
            'entity_id' => $response->json('data.id'),
        ]);
    });
});
