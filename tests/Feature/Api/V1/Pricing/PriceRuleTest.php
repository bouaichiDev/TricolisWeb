<?php

use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceRule;

/**
 * Les règles et les matrices d'un barème.
 *
 * La formule est validée à l'enregistrement (§169U) : la découvrir fautive au
 * moment de facturer se paierait devant un client.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->service = Service::factory()->create([
        'organization_id' => $this->organization->id,
        'code' => 'DEL',
        'name' => 'Livraison',
    ]);

    $this->priceList = PriceList::create([
        'organization_id' => $this->organization->id,
        'code' => 'G2026',
        'name' => 'Barème 2026',
        'scope' => PriceList::GLOBAL,
        'is_active' => true,
    ]);

    $this->addRule = fn (array $payload) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/price-lists/{$this->priceList->id}/rules", $payload);

    $this->addMatrix = fn (array $payload) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/price-lists/{$this->priceList->id}/matrices", $payload);

    $this->rule = fn (string $code = 'POIDS'): PriceRule => PriceRule::create([
        'price_list_id' => $this->priceList->id,
        'code' => $code,
        'name' => $code,
        'formula' => '{V:10}',
        'priority' => 100,
        'is_active' => true,
    ]);
});

describe('règles', function (): void {
    it('ajoute une règle avec sa formule', function (): void {
        ($this->addRule)([
            'code' => 'POIDS',
            'name' => 'Au poids',
            'formula' => '({P:poids}/{V:100})*{V:25}',
            'serviceId' => $this->service->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.formula', '({P:poids}/{V:100})*{V:25}')
            ->assertJsonPath('data.serviceId', $this->service->id);
    });

    /** §169U : une règle sans formule ne calcule rien. */
    it('refuse une règle sans formule', function (): void {
        ($this->addRule)(['code' => 'X', 'name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('formula');
    });

    /** La faute se dit ici, pas au moment de facturer. */
    it('refuse une formule que le moteur ne sait pas lire', function (): void {
        ($this->addRule)(['code' => 'X', 'name' => 'X', 'formula' => 'system("ls")'])
            ->assertStatus(422)->assertJsonValidationErrors('formula');
    });

    /** §169F : les paramètres viennent d'une liste blanche. */
    it('refuse un paramètre inconnu', function (): void {
        ($this->addRule)(['code' => 'X', 'name' => 'X', 'formula' => '{P:temperature}*{V:2}'])
            ->assertStatus(422)->assertJsonValidationErrors('formula');
    });

    it('enregistre les conditions avec la règle', function (): void {
        ($this->addRule)([
            'code' => 'ZONE',
            'name' => 'Zone ouest',
            'formula' => '{V:30}',
            'conditions' => [
                ['variable' => 'code_postal', 'operator' => 'between', 'valueFrom' => '1144', 'valueTo' => '4000'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.conditions.0.variable', 'code_postal')
            ->assertJsonPath('data.conditions.0.valueTo', '4000');
    });

    it('refuse une condition sur une dimension inconnue', function (): void {
        ($this->addRule)([
            'code' => 'X', 'name' => 'X', 'formula' => '{V:1}',
            'conditions' => [['variable' => 'meteo', 'operator' => '=', 'valueFrom' => 'pluie']],
        ])->assertStatus(422)->assertJsonValidationErrors('conditions.0.variable');
    });

    /** Le remplacement plutôt que la fusion : une condition retirée à l'écran
     *  doit disparaître. */
    it('remplace les conditions à la modification', function (): void {
        $rule = ($this->rule)();
        $rule->conditions()->create([
            'variable' => 'poids', 'operator' => '>', 'value_from' => '10',
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/price-rules/{$rule->id}", ['conditions' => []])
            ->assertOk()->assertJsonCount(0, 'data.conditions');
    });

    it('cache la règle d’un barème d’une autre organisation', function (): void {
        $foreign = PriceList::create([
            'organization_id' => Organization::factory()->create()->id,
            'code' => 'X', 'name' => 'X', 'scope' => 'global', 'is_active' => true,
        ]);

        $rule = PriceRule::create([
            'price_list_id' => $foreign->id, 'code' => 'X', 'name' => 'X',
            'formula' => '{V:1}', 'priority' => 100, 'is_active' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/price-rules/{$rule->id}", ['name' => 'Vol'])
            ->assertNotFound();
    });
});

describe('matrices', function (): void {
    it('crée une matrice et ses zones', function (): void {
        $zone1 = ($this->rule)('Z1');
        $zone2 = ($this->rule)('Z2');

        ($this->addMatrix)([
            'code' => 'ZONES',
            'name' => 'Zones NP',
            'rows' => [
                ['label' => 'Zone 1', 'priceRuleId' => $zone1->id, 'rangeFrom' => '1144', 'rangeTo' => '4000'],
                ['label' => 'Zone 2', 'priceRuleId' => $zone2->id, 'rangeFrom' => '4001', 'rangeTo' => '9999'],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.label', 'Zone 1');
    });

    /** Une matrice sans zone ne décide de rien. */
    it('refuse une matrice sans zone', function (): void {
        ($this->addMatrix)(['code' => 'X', 'name' => 'X', 'rows' => []])
            ->assertStatus(422)->assertJsonValidationErrors('rows');
    });

    /**
     * Désigner la règle d'un autre barème ferait appliquer un tarif que
     * celui-ci ne porte pas.
     */
    it('refuse une zone qui désigne la règle d’un autre barème', function (): void {
        $other = PriceList::create([
            'organization_id' => $this->organization->id,
            'code' => 'AUTRE', 'name' => 'Autre', 'scope' => 'global', 'is_active' => true,
        ]);

        $foreignRule = PriceRule::create([
            'price_list_id' => $other->id, 'code' => 'X', 'name' => 'X',
            'formula' => '{V:1}', 'priority' => 100, 'is_active' => true,
        ]);

        ($this->addMatrix)([
            'code' => 'X', 'name' => 'X',
            'rows' => [['label' => 'Z', 'priceRuleId' => $foreignRule->id, 'rangeFrom' => '1']],
        ])->assertStatus(422)->assertJsonValidationErrors('rows.0.priceRuleId');
    });

    /** §169AB : un code postal peut porter un zéro de tête ou des lettres. */
    it('accepte une zone par préfixe', function (): void {
        $rule = ($this->rule)();

        ($this->addMatrix)([
            'code' => 'PREF', 'name' => 'Préfixes',
            'rows' => [[
                'label' => 'Genève', 'priceRuleId' => $rule->id,
                'matchMode' => 'prefix', 'rangeFrom' => '12',
            ]],
        ])->assertCreated()->assertJsonPath('data.rows.0.matchMode', 'prefix');
    });

    /** Une règle citée par une matrice le dit : elle ne vaut que dans ses zones. */
    it('signale qu’une règle est portée par une matrice', function (): void {
        $rule = ($this->rule)();

        ($this->addMatrix)([
            'code' => 'ZONES', 'name' => 'Zones',
            'rows' => [['label' => 'Z', 'priceRuleId' => $rule->id, 'rangeFrom' => '1000', 'rangeTo' => '2000']],
        ])->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/price-lists/{$this->priceList->id}")
            ->assertOk()
            ->assertJsonPath('data.rules.0.matrixDriven', true);
    });
});
