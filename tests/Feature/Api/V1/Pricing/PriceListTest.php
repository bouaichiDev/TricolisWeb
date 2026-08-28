<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceRule;

/**
 * L'API des barèmes.
 *
 * Un barème appartient au transporteur : le §169BJ interdit qu'une
 * organisation voie celui d'une autre, et la réponse est 404 — un 403 en
 * révélerait l'existence.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->list = fn (array $o = []): PriceList => PriceList::create(array_merge([
        'organization_id' => $this->organization->id,
        'code' => 'GLOBAL-2026',
        'name' => 'Barème général 2026',
        'scope' => PriceList::GLOBAL,
        'is_active' => true,
    ], $o));

    $this->post = fn (array $payload) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)->postJson('/api/v1/price-lists', $payload);
});

describe('création', function (): void {
    it('crée un barème global', function (): void {
        ($this->post)([
            'code' => 'G2026',
            'name' => 'Barème général',
            'scope' => 'global',
        ])->assertCreated()->assertJsonPath('data.scope', 'global');
    });

    it('rattache un barème client à ses clients', function (): void {
        ($this->post)([
            'code' => 'IKEA',
            'name' => 'Tarif IKEA',
            'scope' => 'customer',
            'customerIds' => [$this->customer->id],
        ])->assertCreated()->assertJsonPath('data.customers.0.id', $this->customer->id);
    });

    /** Un barème client sans client n'a personne à servir. */
    it('refuse un barème client sans client', function (): void {
        ($this->post)(['code' => 'X', 'name' => 'X', 'scope' => 'customer'])
            ->assertStatus(422)->assertJsonValidationErrors('customerIds');
    });

    /** §169BJ : le client d'une autre organisation n'existe pas ici. */
    it('refuse le client d’une autre organisation', function (): void {
        $foreign = Customer::factory()->create();

        ($this->post)([
            'code' => 'X', 'name' => 'X', 'scope' => 'customer',
            'customerIds' => [$foreign->id],
        ])->assertStatus(422)->assertJsonValidationErrors('customerIds.0');
    });

    it('refuse deux barèmes de même code', function (): void {
        ($this->list)(['code' => 'DOUBLON']);

        ($this->post)(['code' => 'DOUBLON', 'name' => 'X', 'scope' => 'global'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    });
});

describe('lecture', function (): void {
    it('rend le barème avec ses règles', function (): void {
        $list = ($this->list)();

        PriceRule::create([
            'price_list_id' => $list->id,
            'code' => 'POIDS',
            'name' => 'Au poids',
            'formula' => '({P:poids}/{V:100})*{V:25}',
            'priority' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/price-lists/{$list->id}")
            ->assertOk()
            ->assertJsonPath('data.rules.0.formula', '({P:poids}/{V:100})*{V:25}')
            ->assertJsonPath('data.rules.0.matrixDriven', false);
    });

    it('sépare les barèmes globaux des barèmes clients', function (): void {
        ($this->list)(['code' => 'G']);
        ($this->list)(['code' => 'C', 'scope' => PriceList::CUSTOMER]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/price-lists?scope=global')
            ->assertOk()->assertJsonCount(1, 'data');
    });

    it('cache le barème d’une autre organisation', function (): void {
        $foreign = PriceList::create([
            'organization_id' => Organization::factory()->create()->id,
            'code' => 'AILLEURS', 'name' => 'Ailleurs', 'scope' => 'global', 'is_active' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/price-lists/{$foreign->id}")->assertNotFound();
    });
});

describe('modification', function (): void {
    it('modifie le nom et la validité', function (): void {
        $list = ($this->list)();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/price-lists/{$list->id}", [
                'name' => 'Barème 2027',
                'validFrom' => '2027-01-01',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Barème 2027')
            ->assertJsonPath('data.validFrom', '2027-01-01');
    });

    /** La portée ne se change pas : basculer une liste client en globale
     *  l'appliquerait d'un coup à toute la clientèle. */
    it('ignore une tentative de changer la portée', function (): void {
        $list = ($this->list)(['scope' => PriceList::CUSTOMER]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/price-lists/{$list->id}", ['scope' => 'global'])
            ->assertOk()->assertJsonPath('data.scope', 'customer');
    });

    it('supprime un barème', function (): void {
        $list = ($this->list)();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/price-lists/{$list->id}")->assertNoContent();

        $this->assertDatabaseMissing('price_lists', ['id' => $list->id]);
    });
});

describe('validation de formule', function (): void {
    beforeEach(function (): void {
        $this->check = fn (array $payload) => $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson('/api/v1/pricing/formulas/validate', $payload);
    });

    /** §169AF : l'écran de test utilise le moteur du calcul réel. */
    it('valide une formule et l’évalue sur des valeurs d’essai', function (): void {
        ($this->check)([
            'formula' => '({P:poids}/{V:100})*{V:25}',
            'variables' => ['poids' => 350],
        ])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.variables', ['poids'])
            ->assertJsonPath('data.result.amount', '87.50');
    });

    it('valide sans évaluer quand aucune valeur n’est fournie', function (): void {
        ($this->check)(['formula' => '{P:volume}*{V:3}'])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.result', null);
    });

    /** §169AG : la route n'exécute rien, elle lit. */
    it('refuse ce qui n’est pas une formule', function (): void {
        ($this->check)(['formula' => 'system("ls")'])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonMissingPath('data.result.amount');
    });

    /** §169F : les paramètres viennent d'une liste blanche. */
    it('signale un paramètre inconnu', function (): void {
        ($this->check)(['formula' => '{P:temperature}*{V:2}'])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.unknownVariables', ['temperature']);
    });

    it('dit pourquoi l’essai échoue sans invalider la formule', function (): void {
        ($this->check)([
            'formula' => '{P:poids}/{V:0}',
            'variables' => ['poids' => 10],
        ])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.result.amount', null)
            ->assertJsonPath('data.result.error', 'La formule divise par zéro.');
    });
});
