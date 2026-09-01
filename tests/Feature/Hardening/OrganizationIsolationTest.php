<?php

use App\Modules\Claims\Models\Claim;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Models\Document;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Orders\Models\Order;
use App\Modules\Providers\Models\Provider;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Templates\Models\Template;
use App\Modules\Tours\Models\Tour;
use App\Modules\Types\Models\TypeItem;

/**
 * IDOR global — §32.
 *
 * Une seule table de correspondance couvre toutes les ressources de premier
 * niveau : chacune est créée **dans une autre organisation**, puis demandée avec
 * l'en-tête de l'organisation active.
 *
 * La réponse attendue est **404**, jamais 403 : révéler qu'un identifiant existe
 * ailleurs est déjà une fuite. C'est la convention tenue depuis la Phase 4.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    // Client d'une AUTRE organisation, support des ressources qui en dependent.
    $this->foreignCustomer = Customer::factory()->create();
    $this->foreignProvider = Provider::factory()->create();
});

/**
 * @return array<string, callable>
 */
function foreignResources(): array
{
    return [
        'customers' => fn () => Customer::factory()->create(),
        'orders' => fn () => Order::factory()->create(),
        'providers' => fn () => Provider::factory()->create(),
        'drivers' => fn () => Driver::factory()->create(),
        'vehicles' => fn () => Vehicle::factory()->create(),
        'type-items' => fn () => TypeItem::factory()->ofSystemType('vehicle')->create(),
        'tours' => fn () => Tour::factory()->create(),
        'claims' => fn () => Claim::factory()->create(),
        'documents' => fn () => Document::factory()->create(),
        'stock-items' => fn () => StockItem::factory()->create(),
        'customer-import-configurations' => fn () => CustomerImportConfiguration::factory()->create(),
        'customer-api-configurations' => fn () => CustomerApiConfiguration::factory()->create(),
        'customer-export-configurations' => fn () => CustomerExportConfiguration::factory()->create(),
        'export-jobs' => fn () => ExportJob::factory()->create(),
        'templates' => fn () => Template::factory()->create(),
        'communication-rules' => fn () => CommunicationRule::factory()->create(),
        'order-communications' => fn () => OrderCommunication::factory()->create(),
    ];
}

describe('cross organization reads', function (): void {
    it('never reveals a resource belonging to another organization', function (): void {
        foreach (foreignResources() as $segment => $make) {
            $foreign = $make();

            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->getJson("/api/v1/{$segment}/{$foreign->id}")
                ->assertStatus(404);
        }
    });

    it('never lists a resource belonging to another organization', function (): void {
        foreach (foreignResources() as $segment => $make) {
            $foreign = $make();

            $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->getJson("/api/v1/{$segment}")->assertOk();

            $ids = array_column($response->json('data'), 'id');

            expect($ids)->not->toContain($foreign->id, "GET /{$segment} liste une ressource d’une autre organisation");
        }
    });
});

describe('cross organization writes', function (): void {
    it('refuses a foreign customer inside a payload', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', [
                'customerId' => $this->foreignCustomer->id, 'name' => 'Portail',
            ])
            ->assertStatus(422)->assertJsonValidationErrors('customerId');
    });

    it('refuses a foreign order inside a payload', function (): void {
        $foreignOrder = Order::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/order-communications', [
                'orderId' => $foreignOrder->id, 'channel' => 'internal_notification',
                'communicationType' => 'custom', 'recipientRole' => 'custom',
                'recipientName' => 'Libre', 'body' => 'Texte.',
            ])
            ->assertStatus(422)->assertJsonValidationErrors('orderId');
    });

    it('refuses a foreign provider inside a payload', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/drivers', [
                'providerId' => $this->foreignProvider->id,
                'firstName' => 'Ali', 'lastName' => 'Ben',
            ])
            ->assertStatus(422)->assertJsonValidationErrors('providerId');
    });

    it('never writes into a resource of another organization', function (): void {
        $foreign = Customer::factory()->create(['name' => 'Intact']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/customers/{$foreign->id}", ['name' => 'Detourne'])
            ->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/customers/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('customers', ['id' => $foreign->id, 'name' => 'Intact']);
    });
});

describe('organization context', function (): void {
    it('refuses an organization the user does not belong to', function (): void {
        $foreignOrganization = Customer::factory()->create()->organization;

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Organization-Id' => $foreignOrganization->id])
            ->getJson('/api/v1/customers')
            ->assertForbidden();
    });

    it('refuses a malformed organization header', function (): void {
        // Le middleware valide le format avant tout accès : 422, pas 403.
        $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Organization-Id' => 'pas-un-ulid'])
            ->getJson('/api/v1/customers')
            ->assertStatus(422);
    });

    it('refuses every business route without the header', function (): void {
        foreach (['customers', 'orders', 'tours', 'invoices', 'stock-items', 'order-communications'] as $segment) {
            $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/{$segment}")->assertForbidden();
        }
    });
});
