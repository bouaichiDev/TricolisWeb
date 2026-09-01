<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Éprouver une correspondance sur un vrai fichier.
 *
 * C'est ce qui rend une configuration d'import utilisable : sans cela, on peut
 * la décrire mais jamais vérifier qu'elle est juste.
 *
 * **Rien n'est créé.** Chaque test le vérifie sur les tables concernées : une
 * prévisualisation lit, elle n'importe pas.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    // La correspondance de l'ecran : la forme de la charge utile, les noms de
    // colonnes en feuilles.
    $this->configure = fn (array $mapping, string $format = 'csv') => CustomerImportConfiguration::factory()->create([
        'customer_id' => $this->customer->id,
        'file_format' => $format,
        'mapping' => $mapping,
    ]);

    $this->preview = fn (CustomerImportConfiguration $configuration, string $contents, string $name = 'sample.csv') => $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->post(
        "/api/v1/customer-import-configurations/{$configuration->id}/preview",
        ['file' => UploadedFile::fake()->createWithContent($name, $contents)],
    );
});

describe('lecture du fichier', function (): void {
    it('lit un CSV et nomme les colonnes trouvées', function (): void {
        $configuration = ($this->configure)(['externalReference' => 'REF_CDE']);

        $response = ($this->preview)($configuration, "REF_CDE,QTE\nCMD-1,2\nCMD-1,3\n")->assertOk();

        expect($response->json('data.rowCount'))->toBe(2)
            ->and($response->json('data.columns'))->toBe(['REF_CDE', 'QTE']);
    });

    /** Les exports européens emploient le point-virgule aussi souvent. */
    it('reconnaît un CSV à point-virgules', function (): void {
        $configuration = ($this->configure)(['externalReference' => 'REF_CDE']);

        $response = ($this->preview)($configuration, "REF_CDE;QTE\nCMD-1;2\n")->assertOk();

        expect($response->json('data.columns'))->toBe(['REF_CDE', 'QTE'])
            ->and($response->json('data.payload.externalReference'))->toBe('CMD-1');
    });

    it('lit une liste d’objets JSON', function (): void {
        $configuration = ($this->configure)(['externalReference' => 'orderNumber'], 'json');

        $response = ($this->preview)(
            $configuration,
            json_encode([['orderNumber' => 'CMD-9'], ['orderNumber' => 'CMD-9']]),
            'sample.json',
        )->assertOk();

        expect($response->json('data.rowCount'))->toBe(2)
            ->and($response->json('data.payload.externalReference'))->toBe('CMD-9');
    });

    it('refuse un fichier illisible en disant pourquoi', function (): void {
        $configuration = ($this->configure)(['externalReference' => 'a'], 'json');

        $response = ($this->preview)($configuration, '{ pas du json', 'sample.json')
            ->assertStatus(422);

        expect($response->json('message'))->toContain('JSON valide');
    });

    it('refuse un format que rien ne sait lire', function (): void {
        $configuration = ($this->configure)(['externalReference' => 'a'], 'edifact');

        ($this->preview)($configuration, 'quoi que ce soit')->assertStatus(422);
    });

    it('refuse une configuration sans correspondance', function (): void {
        $configuration = ($this->configure)([]);

        ($this->preview)($configuration, "A\n1\n")->assertStatus(422);
    });
});

describe('application de la correspondance', function (): void {
    /**
     * Le cas courant : un fichier plat, une ligne de commande par ligne de
     * fichier.
     */
    it('produit une ligne de commande par ligne de fichier', function (): void {
        $configuration = ($this->configure)([
            'lines' => [['articleCode' => 'ART', 'quantity' => 'QTE']],
        ]);

        $response = ($this->preview)($configuration, "ART,QTE\nA-1,2\nA-2,5\n")->assertOk();

        expect($response->json('data.payload.lines'))->toEqual([
            ['articleCode' => 'A-1', 'quantity' => '2'],
            ['articleCode' => 'A-2', 'quantity' => '5'],
        ]);
    });

    /**
     * Le point qui fait tenir un fichier plat : trois lignes du meme colis ne
     * font qu'un colis. Aucune cle de regroupement n'est declaree — c'est le
     * contenu identique qui les fond.
     */
    it('fond les éléments identiques en un seul', function (): void {
        $configuration = ($this->configure)([
            'lines' => [['articleCode' => 'ART']],
            'packages' => [['key' => 'COLIS', 'reference' => 'COLIS']],
        ]);

        $response = ($this->preview)(
            $configuration,
            "ART,COLIS\nA-1,C-1\nA-2,C-1\nA-3,C-2\n",
        )->assertOk();

        expect($response->json('data.payload.lines'))->toHaveCount(3)
            ->and($response->json('data.payload.packages'))->toEqual([
                ['key' => 'C-1', 'reference' => 'C-1'],
                ['key' => 'C-2', 'reference' => 'C-2'],
            ]);
    });

    /** Un colis servi par plusieurs services, comme livraison puis montage. */
    it('construit les liaisons imbriquées', function (): void {
        $configuration = ($this->configure)([
            'services' => [['serviceNumber' => 'PRESTA', 'packages' => [['packageKey' => 'COLIS']]]],
        ]);

        $response = ($this->preview)(
            $configuration,
            "PRESTA,COLIS\nLIVRAISON,C-1\nMONTAGE,C-1\n",
        )->assertOk();

        expect($response->json('data.payload.services'))->toEqual([
            ['serviceNumber' => 'LIVRAISON', 'packages' => [['packageKey' => 'C-1']]],
            ['serviceNumber' => 'MONTAGE', 'packages' => [['packageKey' => 'C-1']]],
        ]);
    });

    /**
     * Une colonne absente du fichier laisse la clé de côté. La mettre à `null`
     * ferait passer un vide pour une valeur, et la validation se tairait.
     */
    it('omet les colonnes que le fichier ne porte pas', function (): void {
        $configuration = ($this->configure)([
            'lines' => [['articleCode' => 'ART', 'barcode' => 'ABSENTE']],
        ]);

        $response = ($this->preview)($configuration, "ART\nA-1\n")->assertOk();

        expect($response->json('data.payload.lines.0'))->toEqual(['articleCode' => 'A-1']);
    });

    /** Une cellule vide n'est pas une valeur. */
    it('traite une cellule vide comme absente', function (): void {
        $configuration = ($this->configure)(['lines' => [['articleCode' => 'ART', 'barcode' => 'CB']]]);

        $response = ($this->preview)($configuration, "ART,CB\nA-1,\n")->assertOk();

        expect($response->json('data.payload.lines.0'))->toEqual(['articleCode' => 'A-1']);
    });

    /** Le point atteint une valeur imbriquée d'un fichier JSON. */
    it('suit un chemin pointé dans un fichier structuré', function (): void {
        $configuration = ($this->configure)(['customerReference' => 'client.reference'], 'json');

        $response = ($this->preview)(
            $configuration,
            json_encode(['client' => ['reference' => 'CLI-7']]),
            'sample.json',
        )->assertOk();

        expect($response->json('data.payload.customerReference'))->toBe('CLI-7');
    });
});

describe('verdict de validation', function (): void {
    /**
     * Le verdict porte sur les règles réelles de `StoreOrderRequest`. C'est ce
     * qui rend la prévisualisation utile : elle dit ce qui manquerait.
     */
    it('signale les champs obligatoires manquants', function (): void {
        $configuration = ($this->configure)(['lines' => [['articleCode' => 'ART']]]);

        $response = ($this->preview)($configuration, "ART\nA-1\n")->assertOk();

        expect(array_keys($response->json('data.errors')))
            ->toContain('orderDate')
            ->toContain('services')
            ->toContain('lines.0.quantity');
    });

    it('ne signale rien quand la correspondance est complète', function (): void {
        $configuration = ($this->configure)([
            'orderDate' => 'DATE',
            'lines' => [['name' => 'ART', 'quantity' => 'QTE']],
            'services' => [[
                'serviceNumber' => 'PRESTA', 'sequence' => 'SEQ', 'requestedDate' => 'DATE',
                'serviceCode' => 'PRESTA', 'addressCode' => 'ADR',
                'quantity' => 'QTE', 'unit' => 'UNITE', 'requiredTimeMinutes' => 'DUREE',
                'remainingTimeMinutes' => 'DUREE', 'weight' => 'POIDS', 'volume' => 'VOLUME',
                'packageCount' => 'NBCOLIS', 'customerUnitPrice' => 'PU', 'customerTotalPrice' => 'PT',
                'providerUnitCost' => 'PU', 'providerTotalCost' => 'PT', 'status' => 'STATUT',
            ]],
        ]);

        $csv = "DATE,ART,QTE,PRESTA,ADR,SEQ,UNITE,DUREE,POIDS,VOLUME,NBCOLIS,PU,PT,STATUT\n"
            ."2026-09-01,Palette,2,LIVRAISON,QUAI-NORD,1,U,30,12.5,0.4,1,10,20,draft\n";

        $response = ($this->preview)($configuration, $csv)->assertOk();

        expect($response->json('data.errors'))->toBe([]);
    });

    /**
     * Les identifiants de notre base ne sont pas exigés : aucun fichier client
     * ne les porte. Ils sont nommés, pour dire ce qu'un moteur devra résoudre.
     */
    it('nomme les identifiants qu’un moteur devra résoudre', function (): void {
        $configuration = ($this->configure)(['lines' => [['articleCode' => 'ART']]]);

        $response = ($this->preview)($configuration, "ART\nA-1\n")->assertOk();

        expect($response->json('data.resolvedElsewhere'))->toContain('customerId')
            ->and(array_keys($response->json('data.errors')))->not->toContain('customerId');
    });
});

describe('aucune écriture', function (): void {
    /** Prévisualiser n'est pas importer : rien n'est créé, rien n'est stocké. */
    it('ne crée aucune commande', function (): void {
        $configuration = ($this->configure)([
            'orderDate' => 'DATE',
            'lines' => [['name' => 'ART', 'quantity' => 'QTE']],
        ]);

        $before = DB::table('orders')->count();

        ($this->preview)($configuration, "DATE,ART,QTE\n2026-09-01,Palette,2\n")->assertOk();

        expect(DB::table('orders')->count())->toBe($before);
    });

    /**
     * La table ne porte pas d'horodatage : la comparaison se fait sur ce qui
     * pourrait bouger — la correspondance et l'activité.
     */
    it('ne modifie pas la configuration éprouvée', function (): void {
        $configuration = ($this->configure)(['externalReference' => 'REF']);

        ($this->preview)($configuration, "REF\nCMD-1\n")->assertOk();

        $after = $configuration->fresh();

        expect($after->mapping)->toEqual(['externalReference' => 'REF'])
            ->and($after->is_active)->toBe($configuration->is_active)
            ->and($after->name)->toBe($configuration->name);
    });
});

describe('portée', function (): void {
    it('refuse la configuration d’une autre organisation', function (): void {
        $foreign = Customer::factory()->create();
        $configuration = CustomerImportConfiguration::factory()->create([
            'customer_id' => $foreign->id,
            'file_format' => 'csv',
            'mapping' => ['externalReference' => 'REF'],
        ]);

        ($this->preview)($configuration, "REF\nCMD-1\n")->assertNotFound();
    });

    it('refuse sans la permission de consultation', function (): void {
        $configuration = ($this->configure)(['externalReference' => 'REF']);

        // Membre sans aucun droit : ni proprietaire, ni role porteur.
        $powerless = OrganizationUser::factory()
            ->forOrganization($this->organization)
            ->create(['is_owner' => false])
            ->user;

        $this->actingAs($powerless, 'sanctum')->post(
            "/api/v1/customer-import-configurations/{$configuration->id}/preview",
            ['file' => UploadedFile::fake()->createWithContent('s.csv', "REF\nCMD-1\n")],
            $this->headers,
        )->assertForbidden();
    });
});

describe('accord avec l’import', function (): void {
    /**
     * Le pire verdict serait « valide » sur un fichier que l'import refuse :
     * il donne confiance à tort. `serviceId` et `addressId` sont obligatoires
     * et se résolvent depuis le fichier ; sans code, la prévisualisation le dit.
     */
    it('réclame les codes que l’import devra résoudre', function (): void {
        $configuration = ($this->configure)([
            'orderDate' => 'DATE',
            'lines' => [['name' => 'ART', 'quantity' => 'QTE']],
            'services' => [['serviceNumber' => 'PRESTA']],
        ]);

        $response = ($this->preview)($configuration, "DATE,ART,QTE,PRESTA\n2026-09-01,P,1,LIVRAISON\n")
            ->assertOk();

        expect(array_keys($response->json('data.errors')))
            ->toContain('services.0.serviceCode')
            ->toContain('services.0.addressCode');
    });

    /** Un identifiant fourni directement dispense du code. */
    it('accepte un identifiant déjà présent', function (): void {
        $configuration = ($this->configure)([
            'services' => [['serviceId' => 'SID', 'addressId' => 'AID']],
        ]);

        $response = ($this->preview)($configuration, "SID,AID\n01JQZ,01JQY\n")->assertOk();

        expect(array_keys($response->json('data.errors')))
            ->not->toContain('services.0.serviceCode')
            ->not->toContain('services.0.addressCode');
    });
});
