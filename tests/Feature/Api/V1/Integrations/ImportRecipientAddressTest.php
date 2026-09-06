<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;
use App\Shared\Database\MorphMap;
use Illuminate\Http\UploadedFile;

/**
 * L'adresse du **destinataire final**, reprise du fichier.
 *
 * Le code ne convient qu'aux points récurrents — un magasin, un quai — que le
 * client a enregistrés une fois. Une large part du transport ne fonctionne pas
 * ainsi : chaque commande va chez quelqu'un d'autre, et son adresse n'existe
 * nulle part avant le fichier.
 *
 * Ce que ces tests tiennent, et qui n'est pas évident : l'adresse créée
 * **n'appartient pas au client**. Elle appartient à la prestation qui y va. Le
 * carnet d'adresses du donneur d'ordre décrit ses lieux à lui ; y verser mille
 * destinataires ponctuels le rendrait inutilisable.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);

    Service::factory()->create([
        'organization_id' => $this->organization->id,
        'code' => 'LIVRAISON',
    ]);

    // La correspondance porte **les deux** chemins : le code pour les points
    // connus, l'adresse pour les destinataires ponctuels. Une cellule vide vaut
    // « absent », si bien qu'un même fichier emprunte l'un ou l'autre ligne par
    // ligne.
    $this->mapping = [
        'externalReference' => 'REF',
        'orderDate' => 'DATE',
        'lines' => [['name' => 'ART', 'quantity' => 'QTE']],
        'services' => [[
            'serviceNumber' => 'PRESTA', 'sequence' => 'SEQ', 'requestedDate' => 'DATE',
            'serviceCode' => 'PRESTA_CODE', 'addressCode' => 'ADR',
            'address' => [
                'addressLine1' => 'ADR_RUE',
                'postalCode' => 'ADR_CP',
                'city' => 'ADR_VILLE',
                'name' => 'ADR_NOM',
            ],
            'quantity' => 'SQTE', 'unit' => 'UNITE', 'requiredTimeMinutes' => 'DUREE',
            'remainingTimeMinutes' => 'DUREE', 'weight' => 'POIDS', 'volume' => 'VOLUME',
            'packageCount' => 'NBCOLIS', 'customerUnitPrice' => 'PU', 'customerTotalPrice' => 'PT',
            'providerUnitCost' => 'CU', 'providerTotalCost' => 'CT', 'status' => 'STATUT',
        ]],
    ];

    $this->header = 'REF,DATE,ART,QTE,PRESTA,PRESTA_CODE,ADR,ADR_NOM,ADR_RUE,ADR_CP,ADR_VILLE,SEQ,SQTE,UNITE,DUREE,POIDS,VOLUME,NBCOLIS,PU,PT,CU,CT,STATUT';

    /** Une ligne dont le destinataire est décrit en clair, sans code. */
    $this->recipient = fn (string $ref, string $street, string $city = 'Rabat') => "{$ref},2026-09-01,Palette,2,PRESTA-1,LIVRAISON,,Mme Alaoui,{$street},10000,{$city},1,1,U,30,10,0.5,1,50,100,30,60,draft";

    $this->configure = fn () => CustomerImportConfiguration::factory()->create([
        'customer_id' => $this->customer->id,
        'file_format' => 'csv',
        'mapping' => $this->mapping,
        'is_active' => true,
    ]);

    $this->import = fn (CustomerImportConfiguration $configuration, string $contents) => $this
        ->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->post(
            "/api/v1/customer-import-configurations/{$configuration->id}/import",
            [
                'file' => UploadedFile::fake()->createWithContent('import.csv', $contents),
                'agencyId' => $this->agency->id,
            ],
        );
});

describe('l’adresse vient du fichier', function (): void {
    it('crée l’adresse du destinataire et la porte sur la prestation', function (): void {
        $csv = $this->header."\n".($this->recipient)('CMD-1', '12 rue des Oudayas')."\n";

        ($this->import)(($this->configure)(), $csv)->assertCreated();

        $order = Order::where('external_reference', 'CMD-1')->firstOrFail();
        $address = $order->orderServices()->firstOrFail()->address;

        expect($address->address_line_1)->toBe('12 rue des Oudayas')
            ->and($address->city)->toBe('Rabat')
            ->and($address->postal_code)->toBe('10000')
            ->and($address->name)->toBe('Mme Alaoui');
    });

    /**
     * Le point central. Le carnet d'adresses du donneur d'ordre décrit **ses**
     * lieux ; y verser les destinataires d'un fichier le rendrait inutilisable,
     * et laisserait croire que le client travaille avec des gens qu'il ne
     * connaît pas.
     */
    it('ne la verse pas au carnet d’adresses du client', function (): void {
        $csv = $this->header."\n".($this->recipient)('CMD-1', '12 rue des Oudayas')."\n";

        ($this->import)(($this->configure)(), $csv)->assertCreated();

        $address = Order::where('external_reference', 'CMD-1')
            ->firstOrFail()->orderServices()->firstOrFail()->address;

        expect(EntityAddress::where('address_id', $address->id)
            ->where('entity_type', MorphMap::CUSTOMER)->exists())->toBeFalse();
    });

    /**
     * La seule liaison écrite vise l'**organisation**, et elle n'est pas
     * décorative : `OrderScopeGuard` refuse toute adresse qu'aucune liaison ne
     * rattache à l'organisation active — c'est ce qui empêche d'accrocher à sa
     * commande l'adresse d'une autre organisation. Sans elle, l'import
     * échouerait sur `services.0.addressId` un instant après avoir créé
     * l'adresse.
     */
    it('la rattache à l’organisation, et à rien d’autre', function (): void {
        $csv = $this->header."\n".($this->recipient)('CMD-1', '12 rue des Oudayas')."\n";

        ($this->import)(($this->configure)(), $csv)->assertCreated();

        $address = Order::where('external_reference', 'CMD-1')
            ->firstOrFail()->orderServices()->firstOrFail()->address;

        $links = EntityAddress::where('address_id', $address->id)->get();

        expect($links)->toHaveCount(1)
            ->and($links->first()->entity_type)->toBe(MorphMap::ORGANIZATION)
            ->and($links->first()->entity_id)->toBe($this->organization->id)
            ->and($links->first()->organization_id)->toBe($this->organization->id);
    });

    /**
     * Une adresse créée sans rattachement est introuvable par la recherche par
     * code — c'est pourquoi elle n'en porte pas : lui en donner un promettrait
     * une réutilisation qui n'arrivera jamais.
     */
    it('ne lui donne aucun code', function (): void {
        $csv = $this->header."\n".($this->recipient)('CMD-1', '12 rue des Oudayas')."\n";

        ($this->import)(($this->configure)(), $csv)->assertCreated();

        $address = Order::where('external_reference', 'CMD-1')
            ->firstOrFail()->orderServices()->firstOrFail()->address;

        expect($address->code)->toBeNull();
    });

    it('donne à chaque destinataire la sienne', function (): void {
        $csv = $this->header."\n"
            .($this->recipient)('CMD-1', '12 rue des Oudayas')."\n"
            .($this->recipient)('CMD-2', '4 avenue Hassan II', 'Casablanca')."\n";

        ($this->import)(($this->configure)(), $csv)->assertCreated();

        $cities = Order::whereIn('external_reference', ['CMD-1', 'CMD-2'])
            ->get()
            ->map(fn (Order $order) => $order->orderServices()->firstOrFail()->address->city)
            ->sort()
            ->values()
            ->all();

        expect($cities)->toBe(['Casablanca', 'Rabat']);
    });
});

describe('lequel des deux chemins', function (): void {
    /**
     * Une même correspondance sert les deux cas, ligne par ligne : la colonne
     * de code renseignée pour un point connu, vide pour un destinataire
     * ponctuel.
     */
    it('préfère le code quand la colonne le porte', function (): void {
        $known = Address::factory()->create(['code' => 'QUAI-NORD', 'city' => 'Tanger']);
        EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $known->id,
            'entity_type' => MorphMap::CUSTOMER,
            'entity_id' => $this->customer->id,
        ]);

        $withCode = 'CMD-1,2026-09-01,Palette,2,PRESTA-1,LIVRAISON,QUAI-NORD,,,,,1,1,U,30,10,0.5,1,50,100,30,60,draft';

        $csv = $this->header."\n".$withCode."\n".($this->recipient)('CMD-2', '12 rue des Oudayas')."\n";

        ($this->import)(($this->configure)(), $csv)->assertCreated();

        expect(Order::where('external_reference', 'CMD-1')->firstOrFail()
            ->orderServices()->firstOrFail()->address_id)->toBe($known->id);

        expect(Order::where('external_reference', 'CMD-2')->firstOrFail()
            ->orderServices()->firstOrFail()->address->city)->toBe('Rabat');
    });

    /**
     * Un code renseigné mais inconnu **arrête le fichier**, même si l'adresse
     * est là : le deviner créerait un doublon du point que l'utilisateur
     * croyait désigner.
     */
    it('refuse un code inconnu sans se rabattre sur l’adresse', function (): void {
        $line = 'CMD-1,2026-09-01,Palette,2,PRESTA-1,LIVRAISON,INTROUVABLE,Mme Alaoui,12 rue des Oudayas,10000,Rabat,1,1,U,30,10,0.5,1,50,100,30,60,draft';

        ($this->import)(($this->configure)(), $this->header."\n".$line."\n")
            ->assertStatus(422)
            ->assertJsonValidationErrors('orders.0.services.0.addressCode');
    });

    /**
     * Le refus nomme **la colonne** manquante. Laisser la validation parler
     * d'un `addressId` absent enverrait chercher un identifiant Tricolis que le
     * fichier n'a jamais eu à porter.
     */
    it('refuse une adresse sans rue, en nommant ce qui manque', function (): void {
        $line = 'CMD-1,2026-09-01,Palette,2,PRESTA-1,LIVRAISON,,Mme Alaoui,,10000,Rabat,1,1,U,30,10,0.5,1,50,100,30,60,draft';

        ($this->import)(($this->configure)(), $this->header."\n".$line."\n")
            ->assertStatus(422)
            ->assertJsonValidationErrors('orders.0.services.0.address.addressLine1');
    });
});

describe('tout ou rien', function (): void {
    /**
     * La résolution écrit désormais, et elle écrit **avant** la validation. Un
     * fichier refusé laisserait donc des adresses derrière lui — sans commande
     * pour les porter, ni écran pour les retrouver — si la transaction ne les
     * effaçait pas.
     */
    it('n’abandonne aucune adresse derrière un fichier refusé', function (): void {
        $before = Address::count();
        $links = EntityAddress::count();

        // La seconde commande est fautive — quantité nulle — mais la première
        // a déjà fait naître son adresse au moment où la validation échoue.
        $bad = 'CMD-2,2026-09-01,Palette,0,PRESTA-1,LIVRAISON,,Mme Alaoui,4 avenue Hassan II,20000,Casablanca,1,1,U,30,10,0.5,1,50,100,30,60,draft';

        $csv = $this->header."\n".($this->recipient)('CMD-1', '12 rue des Oudayas')."\n".$bad."\n";

        ($this->import)(($this->configure)(), $csv)->assertStatus(422);

        expect(Address::count())->toBe($before)
            ->and(EntityAddress::count())->toBe($links)
            ->and(Order::where('external_reference', 'CMD-1')->exists())->toBeFalse();
    });
});

describe('la prévisualisation', function (): void {
    /**
     * Réclamer `addressCode` alors que l'adresse entière est là annoncerait un
     * manque où tout est présent — le verdict le plus décourageant, puisqu'il
     * envoie corriger ce qui est déjà juste.
     */
    it('ne réclame plus de code quand l’adresse est dans le fichier', function (): void {
        $csv = $this->header."\n".($this->recipient)('CMD-1', '12 rue des Oudayas')."\n";
        $configuration = ($this->configure)();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->post(
            "/api/v1/customer-import-configurations/{$configuration->id}/preview",
            ['file' => UploadedFile::fake()->createWithContent('import.csv', $csv)],
        )->assertOk();

        expect(array_keys($response->json('data.errors')))
            ->not->toContain('services.0.addressCode');
    });

    /** Prévisualiser ne crée rien : l'adresse du fichier reste une intention. */
    it('ne crée aucune adresse', function (): void {
        $before = Address::count();
        $csv = $this->header."\n".($this->recipient)('CMD-1', '12 rue des Oudayas')."\n";
        $configuration = ($this->configure)();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->post(
            "/api/v1/customer-import-configurations/{$configuration->id}/preview",
            ['file' => UploadedFile::fake()->createWithContent('import.csv', $csv)],
        )->assertOk();

        expect(Address::count())->toBe($before);
    });
});
