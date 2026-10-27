<?php

use App\Modules\CustomerPortal\Support\CustomerPortalRoles;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalAfterDelivery;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalTracking;

/**
 * Documents accessibles au client : seulement par un chemin métier (§14, §30).
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->order = CustomerPortalOrders::order('ikea');
    $this->service = $this->order->orderServices()->first();
    $this->headers = CustomerPortal::headers('admin@ikea.test');
});

function portalGet(string $uri, ?array $headers = null): TestResponse
{
    return test()->withHeaders($headers ?? test()->headers)->getJson("/api/v1/customer{$uri}");
}

it('lists the documents of a service, delivery note flagged, without storage path', function (): void {
    $note = CustomerPortalAfterDelivery::document($this->order, MorphMap::ORDER_SERVICE, $this->service->id, 'delivery_note');
    $body = portalGet("/orders/{$this->order->id}/services/{$this->service->id}/documents")->assertOk()
        ->assertJsonPath('data.0.id', $note->id)
        ->assertJsonPath('data.0.isDeliveryNote', true)
        ->getContent();

    expect($body)->not->toContain('storage')->not->toContain($note->storage_path);
});

it('reads a document by every business path of the customer', function (string $path): void {
    $claim = CustomerPortalAfterDelivery::claim('ikea');
    $document = match ($path) {
        'order' => CustomerPortalAfterDelivery::document($this->order, MorphMap::ORDER, $this->order->id),
        'service' => CustomerPortalAfterDelivery::document($this->order, MorphMap::ORDER_SERVICE, $this->service->id),
        'claim' => CustomerPortalAfterDelivery::document($this->order, MorphMap::CLAIM, $claim->id),
        'pod' => CustomerPortalAfterDelivery::proof($this->order)->signatureDocument,
    };

    portalGet("/documents/{$document->id}")->assertOk()->assertJsonPath('data.id', $document->id)->assertJsonMissingPath('data.storagePath');
})->with(['order', 'service', 'claim', 'pod']);

it('refuses a document of another customer, of another organization, or linked nowhere', function (string $case): void {
    $document = match ($case) {
        'qoqa order' => CustomerPortalAfterDelivery::document($qoqa = CustomerPortalOrders::order('qoqa'), MorphMap::ORDER, $qoqa->id),
        'qoqa claim' => CustomerPortalAfterDelivery::document(CustomerPortalOrders::order('qoqa'), MorphMap::CLAIM, CustomerPortalAfterDelivery::claim('qoqa')->id),
        'qoqa pod' => CustomerPortalAfterDelivery::proof(CustomerPortalOrders::order('qoqa'))->photoDocument,
        'other organization' => CustomerPortalAfterDelivery::document($other = Order::factory()->create(), MorphMap::ORDER, $other->id),
        'unlinked' => CustomerPortalAfterDelivery::document($this->order, MorphMap::TOUR, '01J0000000000000000000000X'),
    };

    portalGet("/documents/{$document->id}")->assertNotFound();
    portalGet("/documents/{$document->id}/download")->assertNotFound();
})->with(['qoqa order', 'qoqa claim', 'qoqa pod', 'other organization', 'unlinked']);

it('opens each path only with its own permission', function (): void {
    $orderDocument = CustomerPortalAfterDelivery::document($this->order, MorphMap::ORDER, $this->order->id);
    $signature = CustomerPortalAfterDelivery::proof($this->order)->signatureDocument;
    CustomerPortalTracking::giveRole('viewer@ikea.test', CustomerPortalRoles::STOCK_MANAGER);
    $stock = CustomerPortal::headers('viewer@ikea.test');

    portalGet("/documents/{$orderDocument->id}", $stock)->assertNotFound();
    portalGet("/documents/{$signature->id}", $stock)->assertNotFound();
    portalGet("/orders/{$this->order->id}/services/{$this->service->id}/documents", $stock)->assertForbidden();
});

it('answers 404 for the service of another order', function (): void {
    $qoqaService = CustomerPortalOrders::order('qoqa')->orderServices()->value('id');

    portalGet("/orders/{$this->order->id}/services/{$qoqaService}/documents")->assertNotFound();
    portalGet("/orders/{$this->order->id}/services/pas-un-ulid/documents")->assertNotFound();
});
