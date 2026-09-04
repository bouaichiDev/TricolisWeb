<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceRule;
use App\Modules\Pricing\Models\PricingCalculation;

/**
 * Le prix d'une facture vient du barème, pas de l'écran.
 *
 * C'est le §169AK : le serveur recalcule et valide. Deux calculs vivant en
 * parallèle finiraient par diverger, et c'est la facture qui aurait tort —
 * devant le client.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->delivery = Service::factory()->create([
        'organization_id' => $this->organization->id,
        'code' => 'DEL',
        'name' => 'Livraison',
    ]);

    $this->service = function (array $overrides = []): OrderService {
        $order = Order::factory()->forOrganization($this->organization)
            ->create(['customer_id' => $this->customer->id, 'currency_code' => 'CHF']);

        return OrderService::factory()->create(array_merge([
            'order_id' => $order->id,
            'service_id' => $this->delivery->id,
            'address_id' => Address::factory()->create(['postal_code' => '1204'])->id,
            'status' => 'completed',
            'weight' => 350,
            'customer_unit_price' => 145,
        ], $overrides));
    };

    $this->withRule = function (string $formula): void {
        $list = PriceList::create([
            'organization_id' => $this->organization->id,
            'code' => 'G', 'name' => 'Barème', 'scope' => PriceList::GLOBAL, 'is_active' => true,
        ]);

        PriceRule::create([
            'price_list_id' => $list->id,
            'service_id' => $this->delivery->id,
            'code' => 'POIDS', 'name' => 'Au poids',
            'formula' => $formula, 'priority' => 100, 'is_active' => true,
        ]);
    };

    $this->create = fn (OrderService $service, array $line = []) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson('/api/v1/invoices', [
            'customerId' => $this->customer->id,
            'invoiceDate' => '2026-08-29',
            'currencyCode' => 'CHF',
            'status' => 'draft',
            'lines' => [array_merge([
                'orderServiceId' => $service->id,
                'lineNumber' => 1,
                'description' => 'Livraison',
                'quantity' => 1,
                // Ce que l'ecran propose : le bareme doit primer dessus.
                'unitPrice' => 999,
                'status' => 'billable',
            ], $line)],
        ]);
});

describe('prix calculé', function (): void {
    /** §169AK : le barème l'emporte sur le montant envoyé par l'écran. */
    it('facture au prix du barème, pas à celui de l’écran', function (): void {
        ($this->withRule)('({P:poids}/{V:100})*{V:25}');

        ($this->create)(($this->service)())
            ->assertCreated()
            ->assertJsonPath('data.lines.0.unitPrice', '87.50');
    });

    /** §169M : le calcul appliqué se conserve, avec sa formule. */
    it('historise le calcul appliqué', function (): void {
        ($this->withRule)('({P:poids}/{V:100})*{V:25}');

        ($this->create)(($this->service)())->assertCreated();

        $calculation = PricingCalculation::firstOrFail();

        expect($calculation->result)->toBe('87.50')
            ->and($calculation->formula_snapshot)->toBe('({P:poids}/{V:100})*{V:25}');
    });

    /** Les totaux découlent du prix retenu, pas de celui qu'on a proposé. */
    it('recalcule les totaux sur le prix du barème', function (): void {
        ($this->withRule)('{V:100}');

        ($this->create)(($this->service)(), ['quantity' => 2, 'taxRate' => 10])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.total', '220.00');
    });
});

describe('sans barème', function (): void {
    /**
     * **§169AJ.** Une prestation sans tarif ne se facture pas au hasard : la
     * ligne est refusée, avec le nom de la prestation et la raison.
     */
    it('refuse une ligne dont la prestation n’a aucun tarif', function (): void {
        $service = ($this->service)();

        ($this->create)($service)
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines.0.unitPrice');

        $this->assertDatabaseCount('invoices', 0);
    });

    it('nomme la prestation en cause', function (): void {
        $service = ($this->service)();

        $response = ($this->create)($service)->assertStatus(422);

        // La cle porte des points : `json()` les lirait comme un chemin.
        $message = (string) ($response->json('errors')['lines.0.unitPrice'][0] ?? '');

        expect($message)->toContain($service->service_number)
            ->and($message)->toContain('Tarif non configuré');
    });

    /**
     * §169BO : facturer sans barème reste possible, mais **jamais
     * silencieusement**. C'est une décision, portée par la requête.
     */
    it('retient le prix soumis quand il est assumé', function (): void {
        ($this->create)(($this->service)(), ['priceOverride' => true])
            ->assertCreated()
            ->assertJsonPath('data.lines.0.unitPrice', '999.00');
    });

    /** Le drapeau ne contourne pas un barème : le calcul l'emporte toujours. */
    it('ignore le prix assumé lorsqu’un barème s’applique', function (): void {
        ($this->withRule)('{V:50}');

        ($this->create)(($this->service)(), ['priceOverride' => true])
            ->assertCreated()
            ->assertJsonPath('data.lines.0.unitPrice', '50.00');
    });

    /** Aucun calcul n'a eu lieu : rien à historiser. */
    it('n’historise rien quand le prix est assumé', function (): void {
        ($this->create)(($this->service)(), ['priceOverride' => true])->assertCreated();

        expect(PricingCalculation::count())->toBe(0);
    });

    /** Une formule fautive n'est pas une absence de tarif : on ne bascule pas
     *  sur le prix de la commande sans le dire. */
    it('refuse aussi quand la formule échoue', function (): void {
        ($this->withRule)('{P:distance}*{V:2}');

        ($this->create)(($this->service)())
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines.0.unitPrice');
    });
});

describe('ligne libre', function (): void {
    /** Sans prestation, aucun barème ne s'applique : le prix saisi vaut. */
    it('garde le prix saisi sur une ligne sans prestation', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', [
                'customerId' => $this->customer->id,
                'invoiceDate' => '2026-08-29',
                'currencyCode' => 'CHF',
                'status' => 'draft',
                'lines' => [[
                    'lineNumber' => 1,
                    'description' => 'Frais de dossier',
                    'quantity' => 1,
                    'unitPrice' => 40,
                    'status' => 'billable',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.lines.0.unitPrice', '40.00');
    });
});
