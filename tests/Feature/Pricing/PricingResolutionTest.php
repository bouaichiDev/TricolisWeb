<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;
use App\Modules\Pricing\Actions\CalculateOrderServicePrice;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceMatrix;
use App\Modules\Pricing\Models\PriceMatrixRow;
use App\Modules\Pricing\Models\PriceRule;
use App\Modules\Pricing\Models\PricingCalculation;

/**
 * Le choix du tarif appliqué à une prestation.
 *
 * C'est la partie qui décide combien un client paie. Deux erreurs y coûtent
 * cher : appliquer un tarif global à un client qui a négocié le sien, et
 * facturer zéro parce qu'aucune règle ne correspond.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->calculate = app(CalculateOrderServicePrice::class);

    $this->delivery = Service::factory()->create([
        'organization_id' => $this->organization->id,
        'code' => 'DEL',
        'name' => 'Livraison',
    ]);

    /** Une prestation, avec son poids et son adresse. */
    $this->service = function (array $overrides = [], ?string $postalCode = null): OrderService {
        $order = Order::factory()->forOrganization($this->organization)
            ->create(['customer_id' => $this->customer->id, 'currency_code' => 'CHF']);

        $address = Address::factory()->create(['postal_code' => $postalCode ?? '1204']);

        return OrderService::factory()->create(array_merge([
            'order_id' => $order->id,
            'service_id' => $this->delivery->id,
            'address_id' => $address->id,
            'status' => 'completed',
            'weight' => 350,
        ], $overrides));
    };

    /** Une liste de prix, globale ou attachée au client. */
    $this->list = function (string $scope, string $code): PriceList {
        $list = PriceList::create([
            'organization_id' => $this->organization->id,
            'code' => $code,
            'name' => $code,
            'scope' => $scope,
            'is_active' => true,
        ]);

        if ($scope === PriceList::CUSTOMER) {
            $list->customers()->attach($this->customer->id);
        }

        return $list;
    };

    $this->rule = fn (PriceList $list, string $code, string $formula, array $o = []): PriceRule => PriceRule::create(
        array_merge([
            'price_list_id' => $list->id,
            'service_id' => $this->delivery->id,
            'code' => $code,
            'name' => $code,
            'formula' => $formula,
            'priority' => 100,
            'is_active' => true,
        ], $o)
    );
});

describe('formule seule', function (): void {
    /** L'exemple du §169D : 350 kg, 25 francs par tranche de 100. */
    it('calcule le tarif au poids', function (): void {
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'POIDS', '({P:poids}/{V:100})*{V:25}');

        $outcome = $this->calculate->execute(($this->service)(), $this->organization->id);

        expect($outcome->priced)->toBeTrue()
            ->and($outcome->amount)->toBe('87.50')
            ->and($outcome->pricing->scope())->toBe('global');
    });

    /** §169Z : une matrice n'est jamais nécessaire pour calculer un prix. */
    it('n’exige aucune matrice', function (): void {
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'FIXE', '{V:42}');

        expect($this->calculate->execute(($this->service)(), $this->organization->id)->amount)
            ->toBe('42.00');
    });
});

describe('client avant global', function (): void {
    /** §169P : le tarif négocié l'emporte sur le barème général. */
    it('préfère le tarif du client', function (): void {
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'G25', '({P:poids}/{V:100})*{V:25}');
        ($this->rule)(($this->list)(PriceList::CUSTOMER, 'C'), 'C20', '({P:poids}/{V:100})*{V:20}');

        $outcome = $this->calculate->execute(($this->service)(), $this->organization->id);

        expect($outcome->amount)->toBe('70.00')
            ->and($outcome->pricing->scope())->toBe('customer');
    });

    /** Un client sans tarif propre n'est pas un client sans tarif. */
    it('retombe sur le global quand le client n’a rien', function (): void {
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'G25', '({P:poids}/{V:100})*{V:25}');

        expect($this->calculate->execute(($this->service)(), $this->organization->id)->amount)
            ->toBe('87.50');
    });

    /**
     * **§169CC.** Une règle client partielle ne coupe pas le repli : un client
     * qui a négocié la livraison ne renonce pas au tarif du chargement.
     */
    it('retombe sur le global pour un service que le client n’a pas négocié', function (): void {
        $loading = Service::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'LOAD',
            'name' => 'Chargement',
        ]);

        ($this->rule)(($this->list)(PriceList::CUSTOMER, 'C'), 'C20', '{V:20}');
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'G55', '{V:55}', ['service_id' => $loading->id]);

        $outcome = $this->calculate->execute(
            ($this->service)(['service_id' => $loading->id]),
            $this->organization->id,
        );

        expect($outcome->amount)->toBe('55.00')
            ->and($outcome->pricing->scope())->toBe('global');
    });

    /** Le tarif d'un autre client ne s'applique pas. */
    it('ignore la liste d’un autre client', function (): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $list = ($this->list)(PriceList::CUSTOMER, 'AUTRE');
        $list->customers()->sync([$other->id]);
        ($this->rule)($list, 'X', '{V:1}');

        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'G', '{V:99}');

        expect($this->calculate->execute(($this->service)(), $this->organization->id)->amount)
            ->toBe('99.00');
    });
});

describe('matrice par code postal', function (): void {
    /** L'exemple du §169AC : NP 2000 tombe dans la zone 1144–4000. */
    it('choisit la règle de la zone', function (): void {
        $list = ($this->list)(PriceList::GLOBAL, 'G');

        $zone1 = ($this->rule)($list, 'ZONE1', '({P:poids}/{V:100})*{V:25}');
        $zone2 = ($this->rule)($list, 'ZONE2', '{V:500}');

        $matrix = PriceMatrix::create([
            'price_list_id' => $list->id,
            'service_id' => $this->delivery->id,
            'code' => 'ZONES',
            'name' => 'Zones NP',
            'dimension' => PriceMatrix::POSTAL_CODE,
            'is_active' => true,
        ]);

        PriceMatrixRow::create([
            'price_matrix_id' => $matrix->id, 'price_rule_id' => $zone1->id,
            'label' => 'Zone 1', 'match_mode' => 'numeric',
            'range_from' => '1144', 'range_to' => '4000', 'priority' => 10,
        ]);

        PriceMatrixRow::create([
            'price_matrix_id' => $matrix->id, 'price_rule_id' => $zone2->id,
            'label' => 'Zone 2', 'match_mode' => 'numeric',
            'range_from' => '4001', 'range_to' => '9999', 'priority' => 20,
        ]);

        $outcome = $this->calculate->execute(($this->service)([], '2000'), $this->organization->id);

        expect($outcome->amount)->toBe('87.50')
            ->and($outcome->pricing->row->label)->toBe('Zone 1');

        $far = $this->calculate->execute(($this->service)([], '8000'), $this->organization->id);

        expect($far->amount)->toBe('500.00')
            ->and($far->pricing->row->label)->toBe('Zone 2');
    });

    /**
     * **Un barème par zone serait décoratif** si sa règle restait applicable
     * hors des bornes : un code postal hors zone retomberait dessus par la
     * porte d'à côté, et les plages ne voudraient plus rien dire.
     */
    it('n’applique aucune zone à un code postal hors barème', function (): void {
        $list = ($this->list)(PriceList::GLOBAL, 'G');
        $rule = ($this->rule)($list, 'ZONE1', '{V:25}');

        $matrix = PriceMatrix::create([
            'price_list_id' => $list->id, 'service_id' => null,
            'code' => 'ZONES', 'name' => 'Zones', 'dimension' => 'postal_code', 'is_active' => true,
        ]);

        PriceMatrixRow::create([
            'price_matrix_id' => $matrix->id, 'price_rule_id' => $rule->id,
            'label' => 'Zone 1', 'match_mode' => 'numeric',
            'range_from' => '1000', 'range_to' => '2000', 'priority' => 10,
        ]);

        expect($this->calculate->execute(($this->service)([], '9999'), $this->organization->id)->priced)
            ->toBeFalse();
    });
});

describe('absence de tarif', function (): void {
    /**
     * **§169B.** Zéro reste un prix qu'une formule peut produire ; l'absence de
     * barème n'en est pas un. Les confondre ferait partir des factures à zéro
     * sans que personne ne s'en aperçoive.
     */
    it('ne rend pas zéro quand aucune règle ne correspond', function (): void {
        $outcome = $this->calculate->execute(($this->service)(), $this->organization->id);

        expect($outcome->priced)->toBeFalse()
            ->and($outcome->amount)->toBeNull()
            ->and($outcome->reason)->toBe('Tarif non configuré');
    });

    /** Une formule fautive se distingue d'une absence de tarif : ici quelqu'un
     *  a écrit un barème, et il faut le corriger. */
    it('distingue une formule impossible d’un tarif absent', function (): void {
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'DIST', '{P:distance}*{V:2}');

        $outcome = $this->calculate->execute(($this->service)(), $this->organization->id);

        expect($outcome->priced)->toBeFalse()
            ->and($outcome->pricing)->not->toBeNull()
            ->and($outcome->reason)->toContain('distance');
    });

    it('ne consigne rien quand rien n’est calculé', function (): void {
        $this->calculate->execute(($this->service)(), $this->organization->id);

        expect(PricingCalculation::count())->toBe(0);
    });
});

describe('historique', function (): void {
    /** §169N : la formule est recopiée, pour expliquer le prix plus tard. */
    it('fige la formule et les variables du calcul', function (): void {
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'POIDS', '({P:poids}/{V:100})*{V:25}');

        $service = ($this->service)();
        $this->calculate->execute($service, $this->organization->id);

        $calculation = PricingCalculation::firstOrFail();

        expect($calculation->result)->toBe('87.50')
            ->and($calculation->formula_snapshot)->toBe('({P:poids}/{V:100})*{V:25}')
            ->and($calculation->variables_snapshot['poids'])->toBe('350.000')
            ->and($calculation->scope)->toBe('global')
            ->and($calculation->currency_code)->toBe('CHF');
    });

    /** §169AH : un aperçu ne laisse aucune trace. */
    it('ne consigne rien lorsqu’on demande un simple aperçu', function (): void {
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'POIDS', '{V:10}');

        $this->calculate->execute(($this->service)(), $this->organization->id, record: false);

        expect(PricingCalculation::count())->toBe(0);
    });
});

describe('distance', function (): void {
    beforeEach(function (): void {
        /** Une prestation partant d'un dépôt situé, vers une adresse située. */
        $this->located = function (array $depot, array $delivery): OrderService {
            $agency = Agency::factory()
                ->create(['organization_id' => $this->organization->id]);

            $depotModel = Depot::factory()
                ->create(['agency_id' => $agency->id]);

            $depotAddress = Address::factory()->create($depot);

            EntityAddress::create([
                'organization_id' => $this->organization->id,
                'address_id' => $depotAddress->id,
                'entity_type' => 'depot',
                'entity_id' => $depotModel->id,
                'address_type' => 'main',
                'is_primary' => true,
            ]);

            $order = Order::factory()->forOrganization($this->organization)->create([
                'customer_id' => $this->customer->id,
                'agency_id' => $agency->id,
                'depot_id' => $depotModel->id,
                'currency_code' => 'CHF',
            ]);

            return OrderService::factory()->create([
                'order_id' => $order->id,
                'service_id' => $this->delivery->id,
                'address_id' => Address::factory()->create($delivery)->id,
                'status' => 'completed',
                'weight' => 100,
            ]);
        };
    });

    /**
     * Genève → Lausanne : environ 53 km à vol d'oiseau. Un tarif au kilomètre
     * doit rendre un montant du même ordre, sans quoi la variable ne dit rien.
     */
    it('mesure du dépôt à l’adresse de la prestation', function (): void {
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'KM', '{P:distance}*{V:2}');

        $service = ($this->located)(
            ['latitude' => 46.2044, 'longitude' => 6.1432],
            ['latitude' => 46.5197, 'longitude' => 6.6323],
        );

        $outcome = $this->calculate->execute($service, $this->organization->id);

        expect((float) $outcome->amount)->toBeGreaterThan(100.0)
            ->and((float) $outcome->amount)->toBeLessThan(115.0);
    });

    /** Sans coordonnées, on ne facture pas sur une distance inventée. */
    it('refuse de calculer quand un point manque', function (): void {
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'KM', '{P:distance}*{V:2}');

        $service = ($this->located)(
            ['latitude' => null, 'longitude' => null],
            ['latitude' => 46.5197, 'longitude' => 6.6323],
        );

        $outcome = $this->calculate->execute($service, $this->organization->id);

        expect($outcome->priced)->toBeFalse()
            ->and($outcome->reason)->toContain('distance');
    });

    /** Le point (0,0) est en plein golfe de Guinée : une adresse non géocodée,
     *  pas un lieu de livraison. */
    it('ne prend pas une adresse non géocodée pour un lieu', function (): void {
        ($this->rule)(($this->list)(PriceList::GLOBAL, 'G'), 'KM', '{P:distance}*{V:2}');

        $service = ($this->located)(
            ['latitude' => 0, 'longitude' => 0],
            ['latitude' => 46.5197, 'longitude' => 6.6323],
        );

        expect($this->calculate->execute($service, $this->organization->id)->priced)->toBeFalse();
    });
});
