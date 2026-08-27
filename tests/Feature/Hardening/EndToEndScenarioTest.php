<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Models\Document;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Orders\Models\Service;
use App\Modules\Providers\Models\Provider;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;

/**
 * Scénarios transversaux — §31.
 *
 * Chaque scénario traverse plusieurs modules **par l'API seule**, sans écrire
 * directement en base. C'est ce qui les distingue des tests de module, qui
 * vérifient une ressource à la fois : ils prouvent que les contrats se
 * raccordent — qu'un identifiant retourné par un module est accepté par le
 * suivant, et que les refus métier tiennent d'un bout à l'autre de la chaîne.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->customer = Customer::where('organization_id', $this->organization->id)->firstOrFail();
    $this->agency = Agency::where('organization_id', $this->organization->id)->firstOrFail();

    $this->address = Address::factory()->create();
    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $this->address->id,
        'entity_type' => 'organization',
        'entity_id' => $this->organization->id,
        'address_type' => 'delivery',
        'is_default' => true,
    ]);

    $this->service = Service::create([
        'organization_id' => $this->organization->id, 'code' => 'DELIVERY', 'name' => 'Livraison',
        'unit' => 'delivery', 'default_duration_minutes' => 30, 'billable_to_customer' => true,
        'payable_to_provider' => true, 'requires_address' => true, 'requires_contact' => false,
        'status' => 'active',
    ]);

    $this->api = fn (string $method, string $url, array $payload = []) => $this
        ->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->json($method, $url, $payload);

    $this->orderServicePayload = fn (): array => [
        'serviceId' => $this->service->id, 'addressId' => $this->address->id,
        'serviceNumber' => 'SRV-1', 'sequence' => 1, 'requestedDate' => now()->toDateString(),
        'quantity' => 1, 'unit' => 'delivery', 'requiredTimeMinutes' => 30,
        'remainingTimeMinutes' => 30, 'weight' => 0, 'volume' => 0, 'packageCount' => 0,
        'customerUnitPrice' => 0, 'customerTotalPrice' => 0, 'providerUnitCost' => 0,
        'providerTotalCost' => 0, 'status' => 'draft',
    ];
});

describe('scenario 1 : de la commande a la facturation', function (): void {
    it('traverses order, tour, tracking, proof of delivery and invoice', function (): void {
        // 1. Commande complete : en-tete, ligne et service en une transaction.
        $order = ($this->api)('POST', '/api/v1/orders', [
            'customerId' => $this->customer->id,
            'agencyId' => $this->agency->id,
            'orderDate' => now()->toISOString(),
            'lines' => [['name' => 'Palette de carrelage', 'articleCode' => 'CAR-60', 'quantity' => 4]],
            'services' => [($this->orderServicePayload)()],
        ])->assertCreated();

        $orderId = $order->json('data.id');
        $orderServiceId = $order->json('data.services.0.id');

        expect($orderId)->not->toBeNull()->and($orderServiceId)->not->toBeNull();

        // 2. Tournee, puis arret portant le service de la commande.
        $tourId = ($this->api)('POST', '/api/v1/tours', [
            'tourDate' => now()->addDay()->toDateString(),
            'agencyId' => $this->agency->id, 'status' => 'draft',
        ])->assertCreated()->json('data.id');

        $stopId = ($this->api)('POST', "/api/v1/tours/{$tourId}/stops", [
            'addressId' => $this->address->id, 'sequence' => 1, 'status' => 'pending',
            'services' => [[
                'orderServiceId' => $orderServiceId, 'sequenceWithinStop' => 1, 'status' => 'planned',
            ]],
        ])->assertCreated()->json('data.id');

        // 3. Suivi rattache a la commande et a l'arret.
        ($this->api)('POST', '/api/v1/tracking-events', [
            'orderId' => $orderId, 'tourStopId' => $stopId,
            'eventType' => 'delivery', 'status' => 'done',
            'occurredAt' => now()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.organizationId', $this->organization->id);

        // 4. Preuve de livraison.
        ($this->api)('POST', "/api/v1/orders/{$orderId}/proofs-of-delivery", [
            'recipientName' => 'Karim Bensaïd',
            'deliveredAt' => now()->toIso8601String(),
        ])->assertCreated();

        // 5. Facture dont la ligne s'adosse au service execute.
        $invoiceId = ($this->api)('POST', '/api/v1/invoices', [
            'customerId' => $this->customer->id, 'invoiceNumber' => 'INV-E2E-0001',
            'invoiceDate' => now()->toDateString(), 'currencyCode' => 'MAD', 'status' => 'draft',
            'lines' => [[
                'lineNumber' => 1, 'orderServiceId' => $orderServiceId,
                'description' => 'Livraison Casablanca', 'quantity' => 1,
                'unitPrice' => 450, 'status' => 'billable',
            ]],
        ])->assertCreated()->json('data.id');

        // Les totaux sont calcules, jamais recopies du payload.
        ($this->api)('GET', "/api/v1/invoices/{$invoiceId}")->assertOk()
            ->assertJsonPath('data.customerId', $this->customer->id)
            ->assertJsonPath('data.subtotal', '450.00');

        // 6. Le meme service ne peut pas etre facture deux fois.
        ($this->api)('POST', "/api/v1/invoices/{$invoiceId}/lines", [
            'lineNumber' => 2, 'orderServiceId' => $orderServiceId,
            'description' => 'Doublon', 'quantity' => 1, 'unitPrice' => 10, 'status' => 'billable',
        ])->assertStatus(422);

        // 7. La commande reste lisible avec toute sa chaine.
        ($this->api)('GET', "/api/v1/orders/{$orderId}")->assertOk()
            ->assertJsonPath('data.customerId', $this->customer->id)
            ->assertJsonCount(1, 'data.services');
    });
});

describe('scenario 2 : fournisseur, chauffeur, vehicule, decompte', function (): void {
    it('traverses provider, driver, vehicle and settlement', function (): void {
        $providerId = ($this->api)('POST', '/api/v1/providers', [
            'code' => 'TRANS-E2E', 'name' => 'Transports Atlas', 'status' => 'active',
        ])->assertCreated()->json('data.id');

        // Creer un chauffeur cree aussi son compte : l'identite sert aux deux.
        ($this->api)('POST', '/api/v1/drivers', [
            'providerId' => $providerId, 'code' => 'DRV-E2E-1',
            'firstName' => 'Ali', 'lastName' => 'Ben Salah',
            'email' => 'ali.ben.salah@example.test', 'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.providerId', $providerId)
            ->assertJsonPath('data.name', 'Ali Ben Salah');

        // La source `vehicle` est livree avec l'organisation : on y ajoute une
        // valeur, sans creer de table ni de route.
        $vehicleType = ($this->api)('GET', '/api/v1/types?search=vehicle')
            ->assertOk()->json('data.0.id');

        $vehicleTypeId = ($this->api)('POST', '/api/v1/type-items', [
            'typeId' => $vehicleType, 'code' => 'VL-3T5', 'name' => 'Vehicule leger 3,5 t',
        ])->assertCreated()->json('data.id');

        ($this->api)('POST', '/api/v1/vehicles', [
            'providerId' => $providerId, 'vehicleTypeId' => $vehicleTypeId,
            'code' => 'VEH-E2E-1', 'registrationNumber' => '12345-A-6',
            'payloadCapacity' => 3500, 'volumeCapacity' => 20, 'palletCapacity' => 8,
            'status' => 'active',
        ])->assertCreated();

        $settlementId = ($this->api)('POST', "/api/v1/providers/{$providerId}/settlements", [
            'settlementNumber' => 'STL-E2E-0001', 'status' => 'draft',
            'lines' => [['lineNumber' => 1, 'description' => 'Course Casablanca', 'quantity' => 1, 'unitCost' => 300, 'status' => 'payable']],
        ])->assertCreated()->json('data.id');

        ($this->api)('GET', "/api/v1/provider-settlements/{$settlementId}")->assertOk()
            ->assertJsonPath('data.providerId', $providerId);
    });
});

describe('scenario 3 : stock', function (): void {
    it('traverses item, location, movement, balance and reservation', function (): void {
        $depot = Depot::factory()->forAgency($this->agency)->create();

        $itemId = ($this->api)('POST', '/api/v1/stock-items', [
            'customerId' => $this->customer->id, 'articleCode' => 'REF-E2E-001',
            'description' => 'Carrelage 60x60', 'status' => 'active',
        ])->assertCreated()->json('data.id');

        $locationId = ($this->api)('POST', '/api/v1/stock-locations', [
            'depotId' => $depot->id, 'locationCode' => 'A-01-01', 'status' => 'active',
        ])->assertCreated()->json('data.id');

        // Entree de 100 unites : le solde est produit par le mouvement.
        ($this->api)('POST', '/api/v1/stock-movements', [
            'stockItemId' => $itemId, 'destinationLocationId' => $locationId,
            'movementType' => 'inbound', 'quantity' => 100,
        ])->assertCreated();

        $balances = ($this->api)('GET', "/api/v1/stock-balances?stockItemId={$itemId}")->assertOk();
        expect($balances->json('data.0.quantity'))->toBe('100.000');

        // Une sortie superieure au disponible est refusee en 409 : la demande
        // est comprise, c'est l'etat du stock qui l'interdit.
        ($this->api)('POST', '/api/v1/stock-movements', [
            'stockItemId' => $itemId, 'sourceLocationId' => $locationId,
            'movementType' => 'outbound', 'quantity' => 500,
        ])->assertStatus(409);

        // Le solde n'a pas bouge apres le refus.
        $after = ($this->api)('GET', "/api/v1/stock-balances?stockItemId={$itemId}")->assertOk();
        expect($after->json('data.0.quantity'))->toBe('100.000');
    });
});

describe('scenario 4 : communication', function (): void {
    it('traverses template, rule, communication, attachment and queueing', function (): void {
        $this->customer->update(['email' => 'client@atlas.test']);

        $order = ($this->api)('POST', '/api/v1/orders', [
            'customerId' => $this->customer->id, 'agencyId' => $this->agency->id,
            'orderDate' => now()->toISOString(),
            'lines' => [['name' => 'Colis', 'quantity' => 1]],
            'services' => [($this->orderServicePayload)()],
        ])->assertCreated();

        $orderId = $order->json('data.id');
        $orderNumber = $order->json('data.orderNumber');

        $templateId = ($this->api)('POST', '/api/v1/communication-templates', [
            'code' => 'pod-available-e2e', 'name' => 'POD disponible', 'channel' => 'email',
            'templateType' => 'pod_available', 'subjectTemplate' => 'POD {{ order_number }}',
            'bodyTemplate' => 'Bonjour, votre preuve {{ order_number }} est disponible.',
            'language' => 'fr', 'availableVariables' => ['order_number'],
        ])->assertCreated()->json('data.id');

        ($this->api)('POST', '/api/v1/communication-rules', [
            'templateId' => $templateId, 'eventType' => 'pod_created',
            'recipientRole' => 'customer', 'delayUnit' => 'minutes',
        ])->assertCreated();

        // Le rendu fige le contenu ; le destinataire vient du client.
        $communication = ($this->api)('POST', "/api/v1/orders/{$orderId}/communications", [
            'templateId' => $templateId, 'channel' => 'email',
            'communicationType' => 'pod_available', 'recipientRole' => 'customer',
            'templateVariables' => ['order_number' => $orderNumber],
        ])->assertCreated();

        $communicationId = $communication->json('data.id');

        expect($communication->json('data.subject'))->toBe("POD {$orderNumber}")
            ->and($communication->json('data.recipientEmail'))->toBe('client@atlas.test');

        $document = Document::factory()->forOrganization($this->organization)->create();

        ($this->api)('POST', "/api/v1/order-communications/{$communicationId}/attachments", [
            'documentId' => $document->id,
        ])->assertCreated();

        ($this->api)('POST', "/api/v1/order-communications/{$communicationId}/queue")
            ->assertOk()->assertJsonPath('data.status', 'queued');

        // Engagee, elle n'accepte plus ni piece jointe ni modification.
        $second = Document::factory()->forOrganization($this->organization)->create();

        ($this->api)('POST', "/api/v1/order-communications/{$communicationId}/attachments", [
            'documentId' => $second->id,
        ])->assertStatus(409);

        ($this->api)('PATCH', "/api/v1/order-communications/{$communicationId}", [
            'subject' => 'Reecriture',
        ])->assertStatus(409);
    });
});

describe('scenario 5 : export', function (): void {
    it('traverses configuration and job', function (): void {
        $configurationId = ($this->api)('POST', '/api/v1/customer-export-configurations', [
            'customerId' => $this->customer->id, 'name' => 'Export commandes',
            'exportType' => 'orders', 'format' => 'csv', 'transport' => 'manual',
        ])->assertCreated()->json('data.id');

        $jobId = ($this->api)('POST', '/api/v1/export-jobs', [
            'configurationId' => $configurationId, 'status' => 'pending',
        ])->assertCreated()->json('data.id');

        // Le client est deduit de la configuration ; le chemin reste interne.
        ($this->api)('GET', "/api/v1/export-jobs/{$jobId}")->assertOk()
            ->assertJsonPath('data.customerId', $this->customer->id)
            ->assertJsonPath('data.hasFile', false)
            ->assertJsonMissingPath('data.storagePath');

        // La configuration ayant produit un export n'est plus supprimable.
        ($this->api)('DELETE', "/api/v1/customer-export-configurations/{$configurationId}")
            ->assertStatus(409);
    });
});

describe('isolation des scenarios', function (): void {
    it('never lets a payload reach another organization', function (): void {
        $foreignCustomer = Customer::factory()->create();
        $foreignProvider = Provider::factory()->create();
        $foreignItem = StockItem::factory()->create();
        $foreignLocation = StockLocation::factory()->create();
        $foreignConfiguration = CustomerExportConfiguration::factory()->create();
        $foreignTemplate = CommunicationTemplate::factory()->create();

        ($this->api)('POST', '/api/v1/orders', [
            'customerId' => $foreignCustomer->id, 'agencyId' => $this->agency->id,
            'orderDate' => now()->toISOString(),
            'lines' => [['name' => 'Article', 'quantity' => 1]],
            'services' => [($this->orderServicePayload)()],
        ])->assertStatus(422);

        ($this->api)('POST', '/api/v1/stock-movements', [
            'stockItemId' => $foreignItem->id, 'destinationLocationId' => $foreignLocation->id,
            'movementType' => 'inbound', 'quantity' => 1,
        ])->assertStatus(422);

        ($this->api)('POST', '/api/v1/export-jobs', [
            'configurationId' => $foreignConfiguration->id, 'status' => 'pending',
        ])->assertStatus(422);

        ($this->api)('POST', '/api/v1/drivers', [
            'providerId' => $foreignProvider->id, 'code' => 'DRV-X', 'name' => 'X Y',
            'status' => 'active',
        ])->assertStatus(422);

        ($this->api)('POST', '/api/v1/communication-rules', [
            'templateId' => $foreignTemplate->id, 'eventType' => 'order_created',
            'recipientRole' => 'customer', 'delayUnit' => 'minutes',
        ])->assertStatus(422);
    });
});
