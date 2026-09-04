<?php

use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceRule;
use App\Modules\Pricing\Models\PricingVariable;

/**
 * Le catalogue des variables tarifaires.
 *
 * **Lu par tous, écrit par la plateforme.** Laisser un organisme définir ses
 * variables ferait qu'une même formule ne voudrait plus dire la même chose d'un
 * organisme à l'autre, et ouvrirait le choix de la source — c'est-à-dire des
 * colonnes de la base.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->asAdmin = fn () => $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers);

    /** La plateforme : meme compte, eleve au role plateforme. */
    $this->asPlatform = function () {
        makePlatformAdmin($this->user);

        return $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers);
    };
});

describe('lecture', function (): void {
    /** Sans le catalogue, l'éditeur de formules serait muet. */
    it('rend le catalogue à un administrateur d’organisme', function (): void {
        ($this->asAdmin)()->getJson('/api/v1/pricing-variables')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'poids');
    });

    /** La source est montrée en clair : celui qui écrit une formule doit savoir
     *  ce que `{P:poids}` va chercher. */
    it('montre la table et la colonne de chaque variable', function (): void {
        $response = ($this->asAdmin)()->getJson('/api/v1/pricing-variables')->assertOk();

        $poids = collect($response->json('data'))->firstWhere('code', 'poids');

        expect($poids['sourceTable'])->toBe('order_services')
            ->and($poids['sourceColumn'])->toBe('weight')
            ->and($poids['kind'])->toBe('numeric');
    });

    it('sépare ce qui se calcule de ce qui filtre', function (): void {
        $response = ($this->asAdmin)()->getJson('/api/v1/pricing-variables')->assertOk();

        $postal = collect($response->json('data'))->firstWhere('code', 'code_postal');

        expect($postal['kind'])->toBe('dimension');
    });
});

describe('écriture réservée à la plateforme', function (): void {
    /** **Le point de la demande.** Un administrateur d'organisme ne définit
     *  pas ses variables. */
    it('refuse la création à un administrateur d’organisme', function (): void {
        ($this->asAdmin)()->postJson('/api/v1/pricing-variables', [
            'code' => 'temperature',
            'label' => 'Température',
            'sourceKey' => 'order_service.weight',
        ])->assertForbidden();
    });

    it('refuse aussi la modification et la suppression', function (): void {
        $variable = PricingVariable::where('code', 'poids')->firstOrFail();

        ($this->asAdmin)()->patchJson("/api/v1/pricing-variables/{$variable->id}", ['label' => 'X'])
            ->assertForbidden();

        ($this->asAdmin)()->deleteJson("/api/v1/pricing-variables/{$variable->id}")
            ->assertForbidden();
    });

    it('laisse la plateforme ajouter une variable', function (): void {
        ($this->asPlatform)()->postJson('/api/v1/pricing-variables', [
            'code' => 'poids_commande',
            'label' => 'Poids de la commande',
            'sourceKey' => 'order.weight',
            'unit' => 'kg',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'poids_commande')
            ->assertJsonPath('data.sourceTable', 'orders');
    });

    /** Le genre suit la source : un code postal ne devient pas multipliable
     *  parce qu'on le déclarerait numérique. */
    it('déduit le genre de la source', function (): void {
        ($this->asPlatform)()->postJson('/api/v1/pricing-variables', [
            'code' => 'localite',
            'label' => 'Localité',
            'sourceKey' => 'address.city',
        ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'dimension');
    });
});

describe('garde-fous', function (): void {
    /** §67 : la source vient d'une liste fermée, jamais d'un chemin saisi. */
    it('refuse une source qui n’existe pas', function (): void {
        ($this->asPlatform)()->postJson('/api/v1/pricing-variables', [
            'code' => 'secret',
            'label' => 'Secret',
            'sourceKey' => 'users.password',
        ])->assertStatus(422)->assertJsonValidationErrors('sourceKey');
    });

    /** Un code que le tokenizer ne saurait pas lire rendrait la variable
     *  inutilisable sans qu'on sache pourquoi. */
    it('refuse un code que les formules ne sauraient pas nommer', function (): void {
        foreach (['Mon Poids', '2poids', 'poids-net', ''] as $code) {
            ($this->asPlatform)()->postJson('/api/v1/pricing-variables', [
                'code' => $code,
                'label' => 'X',
                'sourceKey' => 'order_service.weight',
            ])->assertStatus(422)->assertJsonValidationErrors('code');
        }
    });

    it('refuse deux variables de même code', function (): void {
        ($this->asPlatform)()->postJson('/api/v1/pricing-variables', [
            'code' => 'poids',
            'label' => 'Doublon',
            'sourceKey' => 'order_service.weight',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    });

    /**
     * Retirer une variable employée laisserait des barèmes qui ne calculent
     * plus, et l'erreur n'apparaîtrait qu'au moment de facturer.
     */
    it('refuse de supprimer une variable employée par une formule', function (): void {
        $list = PriceList::create([
            'organization_id' => $this->organization->id,
            'code' => 'G', 'name' => 'G', 'scope' => 'global', 'is_active' => true,
        ]);

        PriceRule::create([
            'price_list_id' => $list->id, 'code' => 'R', 'name' => 'R',
            'formula' => '{P:poids}*{V:2}', 'priority' => 100, 'is_active' => true,
        ]);

        $variable = PricingVariable::where('code', 'poids')->firstOrFail();

        ($this->asPlatform)()->deleteJson("/api/v1/pricing-variables/{$variable->id}")
            ->assertStatus(422);
    });

    it('supprime une variable que personne n’emploie', function (): void {
        $variable = PricingVariable::where('code', 'duree')->firstOrFail();

        ($this->asPlatform)()->deleteJson("/api/v1/pricing-variables/{$variable->id}")
            ->assertNoContent();
    });
});

describe('effet sur les formules', function (): void {
    /** Le catalogue fait foi : une variable absente n'est pas nommable. */
    it('refuse une formule qui nomme une variable inconnue', function (): void {
        ($this->asAdmin)()->postJson('/api/v1/pricing/formulas/validate', [
            'formula' => '{P:temperature}*{V:2}',
        ])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.unknownVariables', ['temperature']);
    });

    /** Et une variable ajoutée par la plateforme devient aussitôt utilisable. */
    it('accepte une formule qui nomme une variable fraîchement ajoutée', function (): void {
        ($this->asPlatform)()->postJson('/api/v1/pricing-variables', [
            'code' => 'poids_commande',
            'label' => 'Poids de la commande',
            'sourceKey' => 'order.weight',
        ])->assertCreated();

        ($this->asAdmin)()->postJson('/api/v1/pricing/formulas/validate', [
            'formula' => '{P:poids_commande}*{V:2}',
        ])->assertOk()->assertJsonPath('data.valid', true);
    });

    /** Une variable désactivée cesse d'être nommable : c'est le levier du
     *  superadmin pour retirer une variable sans la supprimer. */
    it('refuse une formule qui nomme une variable désactivée', function (): void {
        $variable = PricingVariable::where('code', 'volume')->firstOrFail();

        ($this->asPlatform)()->patchJson("/api/v1/pricing-variables/{$variable->id}", [
            'isActive' => false,
        ])->assertOk();

        ($this->asAdmin)()->postJson('/api/v1/pricing/formulas/validate', [
            'formula' => '{P:volume}*{V:2}',
        ])->assertOk()->assertJsonPath('data.valid', false);
    });
});
