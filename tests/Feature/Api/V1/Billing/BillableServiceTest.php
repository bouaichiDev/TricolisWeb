<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;

/**
 * Ce qu'on peut encore facturer à un client.
 *
 * La règle vient du statut du service, vérifié et non supposé : le §43 défend de
 * coder en dur `COMPLETED` sans regarder. Est facturable ce qui a été **fait** et
 * ne l'est pas encore — on ne facture pas ce qu'on n'a pas livré.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->serviceFor = function (Customer $customer, string $status = 'completed', array $o = []): OrderService {
        $order = Order::factory()->forOrganization($this->organization)
            ->create(['customer_id' => $customer->id]);

        return OrderService::factory()->create(array_merge([
            'order_id' => $order->id,
            'status' => $status,
        ], $o));
    };

    $this->list = fn (array $query = []) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->getJson("/api/v1/customers/{$this->customer->id}/billable-services?".http_build_query($query));
});

it('propose une prestation terminée', function (): void {
    $service = ($this->serviceFor)($this->customer);

    ($this->list)()->assertOk()->assertJsonPath('data.0.id', $service->id);
});

/** On ne facture pas ce qu'on n'a pas livré. */
it('écarte ce qui n’est pas terminé', function (): void {
    foreach (['pending', 'planned', 'in_progress', 'failed', 'cancelled'] as $status) {
        ($this->serviceFor)($this->customer, $status);
    }

    ($this->list)()->assertOk()->assertJsonCount(0, 'data');
});

/**
 * §10 : un service ne se facture qu'une fois. L'unicité en base le garantit ;
 * ce filtre évite de proposer un choix que la création refuserait.
 */
it('écarte ce qui est déjà facturé', function (): void {
    $service = ($this->serviceFor)($this->customer);

    $invoice = Invoice::factory()->create([
        'organization_id' => $this->organization->id,
        'customer_id' => $this->customer->id,
    ]);

    InvoiceLine::factory()->create([
        'invoice_id' => $invoice->id,
        'order_service_id' => $service->id,
    ]);

    ($this->list)()->assertOk()->assertJsonCount(0, 'data');
});

it('n’expose pas les prestations d’un autre client', function (): void {
    $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
    ($this->serviceFor)($other);

    ($this->list)()->assertOk()->assertJsonCount(0, 'data');
});

it('borne la période sur la date demandée', function (): void {
    ($this->serviceFor)($this->customer, 'completed', ['requested_date' => '2026-08-15']);
    ($this->serviceFor)($this->customer, 'completed', ['requested_date' => '2026-09-15']);

    ($this->list)(['periodFrom' => '2026-09-01', 'periodTo' => '2026-09-30'])
        ->assertOk()->assertJsonCount(1, 'data');
});

/** §44 : un identifiant ne suffit pas à reconnaître une prestation. */
it('montre de quoi décider', function (): void {
    ($this->serviceFor)($this->customer);

    ($this->list)()->assertOk()->assertJsonStructure(['data' => [[
        'id', 'serviceNumber', 'orderNumber', 'customerReference',
        'serviceCode', 'requestedDate', 'quantity', 'customerUnitPrice', 'address',
    ]]]);
});

it('cache le client d’une autre organisation', function (): void {
    $foreign = Customer::factory()->create();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/customers/{$foreign->id}/billable-services")
        ->assertNotFound();
});

describe('filtres de colonne', function (): void {
    /**
     * Le §42 : c'est le serveur qui filtre. Une liste paginée filtrée dans
     * l'écran ne porterait que sur les vingt-cinq lignes affichées.
     */
    it('filtre sur le numéro de commande', function (): void {
        $wanted = ($this->serviceFor)($this->customer);
        ($this->serviceFor)($this->customer);

        ($this->list)(['order' => $wanted->order->order_number])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    });

    /** La colonne montre un numéro et un libellé : chercher dans le seul
     *  numéro rendrait le champ inutile à qui tape « Livraison ». */
    it('filtre sur le libellé du service, pas seulement son numéro', function (): void {
        $service = Service::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Livraison express',
        ]);

        $wanted = ($this->serviceFor)($this->customer, 'completed', ['service_id' => $service->id]);
        ($this->serviceFor)($this->customer);

        ($this->list)(['service' => 'express'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    });

    it('filtre sur la localité de l’adresse', function (): void {
        $address = Address::factory()->create(['city' => 'Genève']);

        $wanted = ($this->serviceFor)($this->customer, 'completed', ['address_id' => $address->id]);
        ($this->serviceFor)($this->customer);

        ($this->list)(['address' => 'Genè'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    });

    it('borne le prix unitaire', function (): void {
        ($this->serviceFor)($this->customer, 'completed', ['customer_unit_price' => 40]);
        $wanted = ($this->serviceFor)($this->customer, 'completed', ['customer_unit_price' => 145]);

        ($this->list)(['priceMin' => 100])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    });

    it('borne la quantité', function (): void {
        ($this->serviceFor)($this->customer, 'completed', ['quantity' => 1]);
        $wanted = ($this->serviceFor)($this->customer, 'completed', ['quantity' => 8]);

        ($this->list)(['quantityMin' => 5])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    });

    /** Une borne haute sous la borne basse ne décrit aucun intervalle : mieux
     *  vaut le refuser que rendre une liste vide sans raison visible. */
    it('refuse un intervalle inversé', function (): void {
        ($this->list)(['priceMin' => 100, 'priceMax' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors('priceMax');
    });
});
