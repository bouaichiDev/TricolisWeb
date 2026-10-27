<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Models\CustomerImportRun;
use App\Modules\Orders\Models\Service;
use App\Shared\Database\MorphMap;
use Illuminate\Http\UploadedFile;

/**
 * L'import « tout ou rien » du back-office et son historique (décision
 * Phase 6 levée le 2026-10-10) : un fichier refusé laisse une trace, sans
 * commande comptée. Même jeu de données que `ImportOrdersTest`.
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

/** Le refus survit à l'annulation : l'historique dit ce qui a été tenté et pourquoi. */
it('garde le fichier refusé dans l’historique, sans commande comptée', function (): void {
    $configuration = ($this->configure)();
    $csv = $this->header.'
'
        .($this->line)('CMD-1', 'Palette').'
'
        .'CMD-2,2026-09-01,Lampe,,LIVRAISON,LIVRAISON,QUAI-NORD,1,1,U,30,10,0.5,1,50,100,30,60,draft
';

    ($this->import)($configuration, $csv)->assertStatus(422);

    $run = CustomerImportRun::sole();
    expect($run->status)->toBe(CustomerImportRun::REJECTED)
        ->and($run->channel)->toBe('internal')
        ->and($run->file_name)->toBe('import.csv')
        ->and($run->created_count)->toBe(0)
        ->and($run->invalid_count)->toBe(1)
        ->and($run->errors[0]['reference'])->toBe('CMD-2')
        ->and($run->triggered_by)->toBe($this->user->id);
});

it('garde aussi un import réussi', function (): void {
    ($this->import)(($this->configure)(), $this->header.'
'.($this->line)('CMD-1', 'Palette').'
')->assertCreated();

    expect(CustomerImportRun::sole()->only(['status', 'created_count']))
        ->toBe(['status' => CustomerImportRun::COMPLETED, 'created_count' => 1]);
});
