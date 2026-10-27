<?php

use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Claims\Models\Claim;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Integrations\Models\CustomerImportRun;
use App\Modules\Orders\Models\Order;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockItem;
use App\Shared\Database\MorphMap;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CustomerIntegrations;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalAfterDelivery;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalStock;
use Tests\Support\PortalRoutes;

/**
 * Isolation entre clients (Phase 7, §5-§6, §41) : IKEA vise, **sur toutes
 * les routes à identifiant**, une ressource de QOQA. Rien ne passe, et la
 * réponse est **identique** à celle d'un identifiant qui n'existe pas — le
 * portail ne révèle même pas qu'elle existe.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    Storage::fake('local');
    Http::preventStrayRequests();
    CustomerPortalStock::enable();

    $order = CustomerPortalOrders::order('qoqa');
    $item = CustomerPortalStock::item('qoqa', 'QOQA-ISO');
    $location = CustomerPortalStock::location('ISO-01');
    $push = CustomerIntegrations::statusPush(CustomerPortal::customer('qoqa'));
    $import = CustomerIntegrations::import('qoqa', 'xlsx');

    $this->qoqa = [
        'order' => $order->id,
        'service' => (string) $order->orderServices()->value('id'),
        'document' => CustomerPortalAfterDelivery::document($order, MorphMap::ORDER, $order->id)->id,
        'claim' => CustomerPortalAfterDelivery::claim('qoqa')->id,
        'proofOfDelivery' => CustomerPortalAfterDelivery::proof($order)->id,
        'stockItem' => $item->id,
        'stockMovement' => CustomerPortalStock::receive($item, $location, '12')->id,
        'stockReservation' => CustomerPortalStock::reserve($item, $location, CustomerPortalStock::line('qoqa'), '2')->id,
        'stockAdjustmentRequest' => StockAdjustmentRequest::factory()->forItem($item)->create([
            'organization_id' => $order->organization_id, 'customer_id' => $item->customer_id, 'requested_by' => authUser()->id,
        ])->id,
        'exportJob' => ExportJob::create([
            'customer_id' => $push->customer_id, 'configuration_id' => $push->id, 'entity_type' => MorphMap::ORDER,
            'entity_id' => $order->id, 'status' => 'failed', 'attempt_count' => 1,
        ])->id,
        'importRun' => CustomerImportRun::create([
            'customer_id' => $import->customer_id, 'configuration_id' => $import->id, 'channel' => 'portal', 'source' => 'excel_import',
            'status' => CustomerImportRun::COMPLETED_WITH_ERRORS, 'errors' => [['index' => 0, 'reference' => 'QOQA-REF-ISO', 'status' => 'invalid', 'errors' => []]],
            'finished_at' => now(),
        ])->id,
        'item' => CustomerCatalogItem::factory()->forCatalog(CustomerCatalog::factory()->forCustomer(CustomerPortal::customer('qoqa'))->create())->create()->id,
        'id' => CustomerPortal::member('admin@qoqa.test')->id,
    ];
    $this->snapshot = fn (): array => [
        Order::find($this->qoqa['order'])?->only(['status', 'updated_at', 'customer_reference']),
        Claim::find($this->qoqa['claim'])?->only(['status', 'updated_at']),
        StockItem::find($this->qoqa['stockItem'])?->only(['description', 'updated_at']),
        ExportJob::find($this->qoqa['exportJob'])?->only(['status', 'attempt_count']),
        CustomerCatalogItem::find($this->qoqa['item'])?->only(['name', 'article_code', 'status', 'updated_at']),
        CustomerPortal::member('admin@qoqa.test')->only(['status', 'updated_at']),
    ];
});

it('ne laisse IKEA atteindre aucune ressource de QOQA, et ne trahit pas son existence', function (): void {
    $before = ($this->snapshot)();
    $headers = CustomerPortal::headers('admin@ikea.test');
    $leaks = [];
    $routes = array_filter(PortalRoutes::authenticated(), static fn (array $route): bool => PortalRoutes::hasParameters($route['uri']));

    foreach ($routes as $route) {
        $foreign = $this->withHeaders($headers)->json($route['method'], PortalRoutes::fill($route['uri'], $this->qoqa), ['description' => 'x', 'reason' => 'x']);
        $nowhere = $this->withHeaders($headers)->json($route['method'], PortalRoutes::fill($route['uri']), ['description' => 'x', 'reason' => 'x']);

        if ($foreign->isSuccessful() || $foreign->status() !== $nowhere->status()) {
            $leaks[] = "{$route['method']} {$route['uri']} → {$foreign->status()} (inexistant : {$nowhere->status()})";
        }
    }

    expect(count($routes))->toBeGreaterThan(30)
        ->and($leaks)->toBe([])
        ->and(($this->snapshot)())->toEqual($before);
});

it('ne laisse aucun filtre sortir du client courant', function (string $uri): void {
    $qoqa = CustomerPortal::customer('qoqa')->id;

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson("{$uri}?customerId={$qoqa}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customerId');
})->with([
    '/api/v1/customer/orders', '/api/v1/customer/claims', '/api/v1/customer/stock/items',
    '/api/v1/customer/stock/movements', '/api/v1/customer/integrations/export-jobs', '/api/v1/customer/integrations/import-runs',
]);

it('ne montre jamais les données de QOQA dans les listes d’IKEA', function (): void {
    $headers = CustomerPortal::headers('admin@ikea.test');
    $bodies = implode('', array_map(
        fn (string $uri): string => (string) $this->withHeaders($headers)->getJson($uri)->getContent(),
        ['/api/v1/customer/orders?perPage=100', '/api/v1/customer/claims', '/api/v1/customer/stock/items', '/api/v1/customer/stock/movements',
            '/api/v1/customer/stock/reservations', '/api/v1/customer/integrations/export-jobs', '/api/v1/customer/integrations/import-runs', '/api/v1/customer/users',
            '/api/v1/customer/calendar/orders?from=2020-01-01&to=2020-03-01', '/api/v1/customer/dashboard'],
    ));

    foreach (array_filter($this->qoqa, static fn (string $key): bool => $key !== 'id', ARRAY_FILTER_USE_KEY) as $name => $id) {
        expect($bodies)->not->toContain($id, "{$name} de QOQA visible chez IKEA");
    }
});
