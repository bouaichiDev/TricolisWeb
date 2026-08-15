<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;
use App\Shared\Database\MorphMap;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->order = Order::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);
    $this->service = Service::factory()->forOrganization($this->organization)->create();

    $this->address = Address::factory()->create();
    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $this->address->id,
        'entity_type' => MorphMap::ORGANIZATION,
        'entity_id' => $this->organization->id,
    ]);

    $this->payload = [
        'serviceId' => $this->service->id, 'addressId' => $this->address->id,
        'serviceNumber' => 'SRV-1', 'sequence' => 1, 'requestedDate' => now()->toDateString(),
        'quantity' => 1, 'unit' => 'delivery', 'requiredTimeMinutes' => 30,
    ];
});

describe('order services', function (): void {
    it('adds a service carrying its own address', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services", $this->payload)
            ->assertCreated()
            ->assertJsonPath('data.addressId', $this->address->id)
            ->assertJsonPath('data.status', 'draft');
    });

    it('separates operational, billing and provider cost blocks', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services", $this->payload + [
                'customerUnitPrice' => 120, 'customerTotalPrice' => 120,
                'providerUnitCost' => 80, 'providerTotalCost' => 80,
            ])->assertCreated();

        expect($response->json('data.operational.requiredTimeMinutes'))->toBe(30)
            ->and($response->json('data.billing.customerTotalPrice'))->toBe('120.00')
            ->and($response->json('data.providerCost.providerTotalCost'))->toBe('80.00');
    });

    it('refuses a service from another organization', function (): void {
        $foreign = Service::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services", ['serviceId' => $foreign->id] + $this->payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('serviceId');
    });

    it('refuses an address outside the organization', function (): void {
        $foreignAddress = Address::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services", ['addressId' => $foreignAddress->id] + $this->payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('addressId');
    });

    it('refuses a duplicated sequence in the same order', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services", $this->payload)->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services", ['serviceNumber' => 'SRV-2'] + $this->payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('sequence');
    });

    it('refuses a time window ending before it starts', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services", $this->payload + [
                'requestedFrom' => now()->addHours(4)->toISOString(),
                'requestedTo' => now()->toISOString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('requestedTo');
    });

    it('changes the status of a service', function (): void {
        $service = OrderService::factory()->create(['order_id' => $this->order->id, 'service_id' => $this->service->id, 'address_id' => $this->address->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/orders/{$this->order->id}/services/{$service->id}/status", ['status' => 'planned'])
            ->assertOk()
            ->assertJsonPath('data.status', 'planned');

        $this->assertDatabaseHas('audit_logs', ['action' => 'status_changed', 'entity_type' => 'order_service']);
    });

    it('hides a service belonging to another order', function (): void {
        $otherOrder = Order::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);
        $foreign = OrderService::factory()->create(['order_id' => $otherOrder->id, 'service_id' => $this->service->id, 'address_id' => $this->address->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$this->order->id}/services/{$foreign->id}")
            ->assertNotFound();
    });
});

describe('order service contacts', function (): void {
    beforeEach(function (): void {
        $this->orderService = OrderService::factory()->create([
            'order_id' => $this->order->id, 'service_id' => $this->service->id, 'address_id' => $this->address->id,
        ]);
    });

    it('snapshots a shared contact so later edits do not rewrite history', function (): void {
        $contact = Contact::factory()->create(['first_name' => 'Amine', 'phone' => '+212600000000']);
        EntityContact::create([
            'organization_id' => $this->organization->id, 'contact_id' => $contact->id,
            'entity_type' => MorphMap::ORGANIZATION, 'entity_id' => $this->organization->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services/{$this->orderService->id}/contacts", [
                'contactId' => $contact->id, 'contactRole' => 'delivery', 'isPrimary' => true,
            ])->assertCreated()->assertJsonPath('data.firstName', 'Amine');

        $contact->update(['first_name' => 'Renommé', 'phone' => '+212611111111']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$this->order->id}/services/{$this->orderService->id}/contacts")
            ->assertOk()
            ->assertJsonPath('data.0.firstName', 'Amine')
            ->assertJsonPath('data.0.phone', '+212600000000');
    });

    it('accepts an ad-hoc contact without a shared record', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services/{$this->orderService->id}/contacts", [
                'firstName' => 'Ponctuel', 'phone' => '+212622222222', 'contactRole' => 'load',
            ])->assertCreated()->assertJsonPath('data.contactId', null);
    });

    it('requires an identity when no shared contact is given', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services/{$this->orderService->id}/contacts", ['phone' => '+212633333333'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('firstName');
    });

    it('keeps a single primary contact per role', function (): void {
        $url = "/api/v1/orders/{$this->order->id}/services/{$this->orderService->id}/contacts";

        $first = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($url, ['firstName' => 'Premier', 'contactRole' => 'delivery', 'isPrimary' => true])->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($url, ['firstName' => 'Second', 'contactRole' => 'delivery', 'isPrimary' => true])->assertCreated();

        $this->assertDatabaseHas('order_service_contacts', ['id' => $first->json('data.id'), 'is_primary' => false]);
        expect($this->orderService->contacts()->where('contact_role', 'delivery')->where('is_primary', true)->count())->toBe(1);
    });

    it('refuses a contact from another organization', function (): void {
        $foreignContact = Contact::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/services/{$this->orderService->id}/contacts", [
                'contactId' => $foreignContact->id, 'firstName' => 'Intrus',
            ])->assertNotFound();
    });
});
