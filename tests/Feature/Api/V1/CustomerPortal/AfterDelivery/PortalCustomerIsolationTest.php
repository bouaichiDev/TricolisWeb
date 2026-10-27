<?php

use App\Shared\Database\MorphMap;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalAfterDelivery;
use Tests\Support\CustomerPortalOrders;

/**
 * Test IDOR systématique de la Phase 4 (§3) : IKEA remplace chaque ULID de
 * l'URL par celui d'une ressource QOQA — toujours 404, jamais une donnée.
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $qoqa = CustomerPortalOrders::order('qoqa');
    $this->qoqa = [
        'order' => $qoqa->id,
        'service' => $qoqa->orderServices()->value('id'),
        'claim' => CustomerPortalAfterDelivery::claim('qoqa')->id,
        'proof' => CustomerPortalAfterDelivery::proof($qoqa)->id,
        'document' => CustomerPortalAfterDelivery::document($qoqa, MorphMap::ORDER, $qoqa->id)->id,
    ];
    $this->ikeaOrder = CustomerPortalOrders::order('ikea')->id;
});

it('answers 404 on every Phase 4 endpoint aimed at a QOQA resource', function (string $method, string $uri): void {
    $uri = strtr($uri, [
        '{order}' => $this->qoqa['order'], '{service}' => $this->qoqa['service'], '{claim}' => $this->qoqa['claim'],
        '{proof}' => $this->qoqa['proof'], '{document}' => $this->qoqa['document'], '{ikea}' => $this->ikeaOrder,
    ]);

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->json($method, "/api/v1/customer{$uri}", ['title' => 'x'])->assertNotFound();
})->with([
    'service documents' => ['GET', '/orders/{order}/services/{service}/documents'],
    'service of another order' => ['GET', '/orders/{ikea}/services/{service}/documents'],
    'order proofs' => ['GET', '/orders/{order}/proofs-of-delivery'],
    'service proofs' => ['GET', '/orders/{order}/services/{service}/proofs-of-delivery'],
    'proof' => ['GET', '/proofs-of-delivery/{proof}'],
    'document' => ['GET', '/documents/{document}'],
    'download' => ['GET', '/documents/{document}/download'],
    'claim' => ['GET', '/claims/{claim}'],
    'claim update' => ['PATCH', '/claims/{claim}'],
    'claim documents' => ['GET', '/claims/{claim}/documents'],
]);

it('never lists a QOQA claim for IKEA, even filtered on the QOQA order', function (): void {
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson('/api/v1/customer/claims?orderId='.$this->qoqa['order'])
        ->assertOk()->assertJsonPath('meta.total', 0);
});
