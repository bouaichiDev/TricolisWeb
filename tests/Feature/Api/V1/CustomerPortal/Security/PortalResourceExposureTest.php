<?php

use App\Shared\Database\MorphMap;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CustomerIntegrations;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalAfterDelivery;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalStock;

/**
 * Minimisation (Phase 7, §21, §27, §50) : aucune réponse du portail ne porte
 * un champ interne ou secret, à aucune profondeur. Le contrôle porte sur les
 * **clés** du JSON rendu, endpoint par endpoint, avec des données réelles.
 */
const FORBIDDEN_KEYS = [
    'password', 'passwordHash', 'password_hash', 'rememberToken', 'remember_token', 'apiKey', 'apiKeyHash', 'api_key_hash',
    'encryptedPassword', 'encrypted_password', 'encryptedCredentials', 'encrypted_credentials', 'storagePath', 'storage_path',
    'internalRemark', 'internal_remark', 'providerUnitCost', 'providerTotalCost', 'provider_unit_cost', 'provider_total_cost',
    'purchasePrice', 'purchase_price', 'username', 'host', 'settings', 'tokenHash', 'token_hash',
];

beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    Storage::fake('local');
    CustomerPortalStock::enable();

    $this->order = CustomerPortalOrders::order('ikea');
    $this->document = CustomerPortalAfterDelivery::document($this->order, MorphMap::ORDER, $this->order->id);
    $this->claim = CustomerPortalAfterDelivery::claim('ikea');
    CustomerPortalAfterDelivery::proof($this->order);
    $item = CustomerPortalStock::item('ikea', 'EXPO-1');
    CustomerPortalStock::receive($item, CustomerPortalStock::location('EXP-01'), '5');
    CustomerIntegrations::statusPush(CustomerPortal::customer('ikea'), ['clientSecret' => 'ne-pas-montrer'], ['username' => 'compte', 'host' => 'https://partner.example.com']);
    CustomerIntegrations::import('ikea', 'xlsx');
});

/**
 * @return list<string>
 */
function exposedKeys(mixed $node, string $path = ''): array
{
    if (! is_array($node)) {
        return [];
    }

    $found = [];

    foreach ($node as $key => $value) {
        $current = $path === '' ? (string) $key : "{$path}.{$key}";

        if (is_string($key) && in_array($key, FORBIDDEN_KEYS, true)) {
            $found[] = $current;
        }

        $found = [...$found, ...exposedKeys($value, $current)];
    }

    return $found;
}

it('ne rend aucun champ interne ou secret dans les réponses du portail', function (): void {
    $headers = CustomerPortal::headers('admin@ikea.test');
    $order = $this->order->id;
    $service = (string) $this->order->orderServices()->value('id');
    $uris = [
        '/api/v1/customer/context', '/api/v1/customer/auth/me', '/api/v1/customer/profile', '/api/v1/customer/dashboard',
        '/api/v1/customer/orders', "/api/v1/customer/orders/{$order}", "/api/v1/customer/orders/{$order}/lines",
        "/api/v1/customer/orders/{$order}/packages", "/api/v1/customer/orders/{$order}/services", "/api/v1/customer/orders/{$order}/history",
        "/api/v1/customer/orders/{$order}/documents", "/api/v1/customer/orders/{$order}/proofs-of-delivery",
        "/api/v1/customer/orders/{$order}/tracking", "/api/v1/customer/orders/{$order}/services/{$service}/documents",
        "/api/v1/customer/documents/{$this->document->id}", '/api/v1/customer/claims', "/api/v1/customer/claims/{$this->claim->id}",
        '/api/v1/customer/users', '/api/v1/customer/roles', '/api/v1/customer/order-form-options', '/api/v1/customer/catalog-items',
        '/api/v1/customer/calendar/orders?from=2026-01-01&to=2026-03-31', '/api/v1/customer/stock/summary', '/api/v1/customer/stock/items',
        '/api/v1/customer/stock/movements', '/api/v1/customer/stock/reservations', '/api/v1/customer/integrations',
        '/api/v1/customer/integrations/import-configurations', '/api/v1/customer/integrations/export-configurations',
        '/api/v1/customer/integrations/api-configurations', '/api/v1/customer/integrations/export-jobs',
    ];
    $exposed = [];

    foreach ($uris as $uri) {
        $response = $this->withHeaders($headers)->getJson($uri);
        expect($response->status())->toBe(200, "{$uri} : {$response->status()}");

        foreach (exposedKeys($response->json()) as $key) {
            $exposed[] = "{$uri} → {$key}";
        }
    }

    expect($exposed)->toBe([]);
});
