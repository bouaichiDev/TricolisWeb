<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Database\MorphMap;
use Illuminate\Http\UploadedFile;

/**
 * Importer réellement un fichier client.
 *
 * La prévisualisation dit ce qu'une correspondance produirait ; ceci l'écrit.
 * Le comportement qui compte est le **tout ou rien** : un fichier à moitié
 * importé laisserait un état que personne ne saurait reprendre, et le §4
 * interdit la table d'historique qui permettrait de le rattraper.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);

    // Les deux references que le fichier porte en clair, et que l'import doit
    // traduire en identifiants.
    $this->service = Service::factory()->create([
        'organization_id' => $this->organization->id,
        'code' => 'LIVRAISON',
    ]);

    $this->address = Address::factory()->create(['code' => 'QUAI-NORD']);
    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $this->address->id,
        'entity_type' => MorphMap::CUSTOMER,
        'entity_id' => $this->customer->id,
    ]);

    // Une correspondance complete : tout ce que `StoreOrderRequest` exige, sauf
    // les identifiants que le fichier ne porte pas.
    $this->mapping = [
        'externalReference' => 'REF',
        'orderDate' => 'DATE',
        'lines' => [['name' => 'ART', 'quantity' => 'QTE']],
        'services' => [[
            'serviceNumber' => 'PRESTA', 'sequence' => 'SEQ', 'requestedDate' => 'DATE',
            'serviceCode' => 'PRESTA_CODE', 'addressCode' => 'ADR',
            'quantity' => 'SQTE', 'unit' => 'UNITE', 'requiredTimeMinutes' => 'DUREE',
            'remainingTimeMinutes' => 'DUREE', 'weight' => 'POIDS', 'volume' => 'VOLUME',
            'packageCount' => 'NBCOLIS', 'customerUnitPrice' => 'PU', 'customerTotalPrice' => 'PT',
            'providerUnitCost' => 'CU', 'providerTotalCost' => 'CT', 'status' => 'STATUT',
        ]],
    ];

    $this->header = 'REF,DATE,ART,QTE,PRESTA,PRESTA_CODE,ADR,SEQ,SQTE,UNITE,DUREE,POIDS,VOLUME,NBCOLIS,PU,PT,CU,CT,STATUT';
    $this->line = fn (string $ref, string $article) => "{$ref},2026-09-01,{$article},2,LIVRAISON,LIVRAISON,QUAI-NORD,1,1,U,30,10,0.5,1,50,100,30,60,draft";

    $this->configure = fn (array $overrides = []) => CustomerImportConfiguration::factory()->create(array_merge([
        'customer_id' => $this->customer->id,
        'file_format' => 'csv',
        'mapping' => $this->mapping,
        'is_active' => true,
    ], $overrides));

    $this->import = fn (CustomerImportConfiguration $configuration, string $contents, array $payload = []) => $this
        ->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->post(
            "/api/v1/customer-import-configurations/{$configuration->id}/import",
            array_merge([
                'file' => UploadedFile::fake()->createWithContent('import.csv', $contents),
                'agencyId' => $this->agency->id,
            ], $payload),
        );
});

describe('création des commandes', function (): void {
    it('crée une commande à partir d’un fichier', function (): void {
        $configuration = ($this->configure)();
        $csv = $this->header."\n".($this->line)('CMD-1', 'Palette')."\n";

        $response = ($this->import)($configuration, $csv)->assertCreated();

        expect($response->json('data.orders'))->toHaveCount(1)
            ->and(Order::where('external_reference', 'CMD-1')->exists())->toBeTrue();
    });

    /**
     * Un fichier plat porte souvent plusieurs commandes : les lignes qui
     * partagent leur référence appartiennent à la même.
     */
    it('sépare les commandes par leur référence', function (): void {
        $configuration = ($this->configure)();
        $csv = $this->header."\n"
            .($this->line)('CMD-1', 'Palette')."\n"
            .($this->line)('CMD-1', 'Carton')."\n"
            .($this->line)('CMD-2', 'Lampe')."\n";

        $response = ($this->import)($configuration, $csv)->assertCreated();

        expect($response->json('data.orders'))->toHaveCount(2);

        $first = Order::where('external_reference', 'CMD-1')->firstOrFail();
        expect($first->lines()->count())->toBe(2);
    });

    /** Le client vient de la configuration : le fichier ne le porte pas. */
    it('rattache les commandes au client de la configuration', function (): void {
        $configuration = ($this->configure)();

        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'Palette')."\n")
            ->assertCreated();

        expect(Order::where('external_reference', 'CMD-1')->firstOrFail()->customer_id)
            ->toBe($this->customer->id);
    });

    /** `source` dit d'où vient la commande : c'est ce qui la rend retrouvable. */
    it('marque la commande du format dont elle vient', function (): void {
        $configuration = ($this->configure)();

        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'Palette')."\n")
            ->assertCreated();

        expect(Order::where('external_reference', 'CMD-1')->firstOrFail()->source->value)
            ->toBe('csv_import');
    });

    it('marque json_import pour un fichier JSON', function (): void {
        $configuration = ($this->configure)(['file_format' => 'json']);

        $rows = [[
            'REF' => 'CMD-J', 'DATE' => '2026-09-01', 'ART' => 'Palette', 'QTE' => 2,
            'PRESTA' => 'LIVRAISON', 'PRESTA_CODE' => 'LIVRAISON', 'ADR' => 'QUAI-NORD',
            'SEQ' => 1, 'SQTE' => 1, 'UNITE' => 'U', 'DUREE' => 30,
            'POIDS' => 10, 'VOLUME' => 0.5, 'NBCOLIS' => 1, 'PU' => 50, 'PT' => 100,
            'CU' => 30, 'CT' => 60, 'STATUT' => 'draft',
        ]];

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->post(
            "/api/v1/customer-import-configurations/{$configuration->id}/import",
            [
                'file' => UploadedFile::fake()->createWithContent('import.json', json_encode($rows)),
                'agencyId' => $this->agency->id,
            ],
        )->assertCreated();

        expect(Order::where('external_reference', 'CMD-J')->firstOrFail()->source->value)
            ->toBe('json_import');
    });

    /**
     * `orders.agency_id` est `NOT NULL` : une commande sans agence n'existe
     * pas, et le fichier d'un client ne la porte pas.
     */
    it('exige une agence', function (): void {
        $configuration = ($this->configure)();

        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'Palette')."\n", ['agencyId' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('agencyId');
    });

    /** Le dépôt, lui, est facultatif : une commande peut attendre son affectation. */
    it('accepte une commande sans dépôt', function (): void {
        $configuration = ($this->configure)();

        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'Palette')."\n")
            ->assertCreated();

        expect(Order::where('external_reference', 'CMD-1')->firstOrFail()->depot_id)->toBeNull();
    });
});

describe('tout ou rien', function (): void {
    /**
     * Le comportement central : une commande fautive fait refuser le fichier
     * entier. Sans table d'historique, un import partiel serait irrattrapable.
     */
    it('ne crée rien quand une seule commande est invalide', function (): void {
        $configuration = ($this->configure)();

        // La seconde commande n'a pas de quantite : elle sera refusee.
        $csv = $this->header."\n"
            .($this->line)('CMD-1', 'Palette')."\n"
            ."CMD-2,2026-09-01,Lampe,,LIVRAISON,LIVRAISON,QUAI-NORD,1,1,U,30,10,0.5,1,50,100,30,60,draft\n";

        $before = Order::count();

        ($this->import)($configuration, $csv)->assertStatus(422);

        expect(Order::count())->toBe($before);
    });

    /** L'erreur dit **laquelle** des commandes du fichier est fautive. */
    it('désigne la commande fautive par son rang', function (): void {
        $configuration = ($this->configure)();

        $csv = $this->header."\n"
            .($this->line)('CMD-1', 'Palette')."\n"
            ."CMD-2,2026-09-01,Lampe,,LIVRAISON,LIVRAISON,QUAI-NORD,1,1,U,30,10,0.5,1,50,100,30,60,draft\n";

        $response = ($this->import)($configuration, $csv)->assertStatus(422);

        expect(array_keys($response->json('errors')))->toContain('orders.1.lines.0.quantity');
    });
});

describe('refus', function (): void {
    it('refuse une configuration désactivée', function (): void {
        $configuration = ($this->configure)(['is_active' => false]);

        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'P')."\n")
            ->assertStatus(422);
    });

    it('refuse une configuration sans correspondance', function (): void {
        $configuration = ($this->configure)(['mapping' => null]);

        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'P')."\n")
            ->assertStatus(422);
    });

    it('refuse la configuration d’une autre organisation', function (): void {
        $foreign = Customer::factory()->create();
        $configuration = CustomerImportConfiguration::factory()->create([
            'customer_id' => $foreign->id,
            'file_format' => 'csv',
            'mapping' => $this->mapping,
        ]);

        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'P')."\n")
            ->assertNotFound();
    });

    /**
     * Importer, c'est créer des commandes : lire les intégrations ne suffit
     * pas, et un fichier ne doit pas contourner `orders.create`.
     */
    it('refuse sans le droit de créer une commande', function (): void {
        $configuration = ($this->configure)();

        $powerless = OrganizationUser::factory()
            ->forOrganization($this->organization)
            ->create(['is_owner' => false])
            ->user;

        $this->actingAs($powerless, 'sanctum')->post(
            "/api/v1/customer-import-configurations/{$configuration->id}/import",
            [
                'file' => UploadedFile::fake()->createWithContent('i.csv', $this->header."\n".($this->line)('CMD-1', 'P')."\n"),
                'agencyId' => $this->agency->id,
            ],
            $this->headers,
        )->assertForbidden();
    });
});

describe('retrouver les commandes importées', function (): void {
    /** C'est `source` qui les distingue des commandes saisies à la main. */
    it('filtre la liste sur les commandes importées', function (): void {
        $configuration = ($this->configure)();
        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'P')."\n")->assertCreated();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/orders?source=csv_import')->assertOk();

        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.externalReference'))->toBe('CMD-1');
    });

    /**
     * Ce qui reste à faire sur une commande importée, c'est lui donner un
     * dépôt : le filtre les rassemble.
     */
    it('filtre les commandes sans dépôt', function (): void {
        $configuration = ($this->configure)();
        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'P')."\n")->assertCreated();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/orders?withoutDepot=1')->assertOk();

        expect($response->json('data.0.depotId'))->toBeNull();
    });

    /** Sans le filtre, les commandes avec dépôt restent visibles. */
    it('ne filtre rien quand le drapeau est absent', function (): void {
        $configuration = ($this->configure)();
        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'P')."\n")->assertCreated();

        $withDepot = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/orders')->assertOk();

        expect($withDepot->json('meta.total'))->toBeGreaterThanOrEqual(1);
    });
});

describe('résolution des références', function (): void {
    /**
     * Le chaînon qui manquait : le fichier dit `LIVRAISON`, la base attend un
     * ULID. Un code inconnu arrête le fichier plutôt que d'être deviné.
     */
    it('refuse un code de prestation inconnu', function (): void {
        $configuration = ($this->configure)();

        $csv = $this->header."\n"
            ."CMD-1,2026-09-01,Palette,2,LIVRAISON,INCONNU,QUAI-NORD,1,1,U,30,10,0.5,1,50,100,30,60,draft\n";

        $response = ($this->import)($configuration, $csv)->assertStatus(422);

        expect(array_keys($response->json('errors')))->toContain('orders.0.services.0.serviceCode');
    });

    /** Une adresse d'un autre client n'est pas atteignable par son code. */
    it('refuse l’adresse d’un autre client', function (): void {
        $configuration = ($this->configure)();

        $foreignAddress = Address::factory()->create(['code' => 'AILLEURS']);
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $foreignAddress->id,
            'entity_type' => MorphMap::CUSTOMER,
            'entity_id' => $otherCustomer->id,
        ]);

        $csv = $this->header."\n"
            ."CMD-1,2026-09-01,Palette,2,LIVRAISON,LIVRAISON,AILLEURS,1,1,U,30,10,0.5,1,50,100,30,60,draft\n";

        $response = ($this->import)($configuration, $csv)->assertStatus(422);

        expect(array_keys($response->json('errors')))->toContain('orders.0.services.0.addressCode');
    });

    it('remplace le code par l’identifiant de la prestation', function (): void {
        $configuration = ($this->configure)();

        ($this->import)($configuration, $this->header."\n".($this->line)('CMD-1', 'Palette')."\n")
            ->assertCreated();

        $order = Order::where('external_reference', 'CMD-1')->firstOrFail();

        expect($order->orderServices()->first()->service_id)->toBe($this->service->id);
    });
});
