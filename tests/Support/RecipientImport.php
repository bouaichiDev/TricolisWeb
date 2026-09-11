<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Un fichier d'import qui distingue le donneur d'ordre du client final.
 *
 * La correspondance porte **les deux** chemins : `ADR` pour un point enregistré
 * du donneur d'ordre, les colonnes `DEST_*` pour un client final décrit en
 * entier. Une cellule vide vaut « absent », si bien qu'un même fichier emprunte
 * l'un ou l'autre ligne par ligne.
 *
 * Une ligne se décrit par **ce qui change** : `csv(['DEST_TEL' => ''])` plutôt
 * qu'une chaîne de trente valeurs où personne ne retrouve la sixième.
 */
final class RecipientImport
{
    /** @var array<string, string> */
    public const array ROW = [
        'REF' => 'CMD-1', 'DATE' => '2026-09-01', 'ART' => 'Palette', 'QTE' => '2',
        'PRESTA' => 'PRESTA-1', 'PRESTA_CODE' => 'LIVRAISON', 'ADR' => '',
        'DEST_PRENOM' => 'Amina', 'DEST_NOM' => 'Alaoui', 'DEST_SOCIETE' => '',
        'DEST_MAIL' => 'amina@example.test', 'DEST_TEL' => '+41 22 555 01 01',
        'DEST_RUE' => '12 rue du Rhône', 'DEST_CP' => '1204', 'DEST_VILLE' => 'Genève', 'DEST_PAYS' => 'ch',
        'SEQ' => '1', 'SQTE' => '1', 'UNITE' => 'U', 'DUREE' => '30', 'POIDS' => '10', 'VOLUME' => '0.5',
        'NBCOLIS' => '1', 'PU' => '50', 'PT' => '100', 'CU' => '30', 'CT' => '60', 'STATUT' => 'draft',
    ];

    /** Le client final, vidé : la ligne ne va plus que par son code. */
    public const array NO_RECIPIENT = [
        'DEST_PRENOM' => '', 'DEST_NOM' => '', 'DEST_MAIL' => '', 'DEST_TEL' => '',
        'DEST_RUE' => '', 'DEST_CP' => '', 'DEST_VILLE' => '', 'DEST_PAYS' => '',
    ];

    /**
     * Client, agence, prestation — et de quoi importer ou éprouver un fichier.
     */
    public static function prepare(TestCase $test): void
    {
        $test->seed();
        $test->user = authUser();
        $test->organization = authOrganization();
        $test->customer = Customer::factory()->create(['organization_id' => $test->organization->id]);
        $test->agency = Agency::factory()->create(['organization_id' => $test->organization->id]);

        Service::factory()->create(['organization_id' => $test->organization->id, 'code' => 'LIVRAISON']);
    }

    /** @param  array<string, mixed>|null  $mapping */
    public static function configure(TestCase $test, ?array $mapping = null): CustomerImportConfiguration
    {
        return CustomerImportConfiguration::factory()->create([
            'customer_id' => $test->customer->id,
            'file_format' => 'csv',
            'mapping' => $mapping ?? self::mapping(),
            'is_active' => true,
        ]);
    }

    /** @param  'import'|'preview'  $endpoint */
    public static function send(TestCase $test, string $csv, string $endpoint = 'import', ?CustomerImportConfiguration $configuration = null): TestResponse
    {
        $configuration ??= self::configure($test);

        return $test->actingAs($test->user, 'sanctum')
            ->withHeaders(['X-Organization-Id' => $test->organization->id])
            ->post("/api/v1/customer-import-configurations/{$configuration->id}/{$endpoint}", [
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
                'agencyId' => $test->agency->id,
            ]);
    }

    /** @param  array<string, string>  ...$rows  ce qui diffère de `ROW` */
    public static function csv(array ...$rows): string
    {
        $lines = [implode(',', array_keys(self::ROW))];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_merge(self::ROW, $row));
        }

        return implode("\n", $lines)."\n";
    }

    public static function service(string $reference): OrderService
    {
        return Order::where('external_reference', $reference)->firstOrFail()->orderServices()->firstOrFail();
    }

    /** @return array<string, mixed> */
    public static function mapping(): array
    {
        return [
            'externalReference' => 'REF',
            'orderDate' => 'DATE',
            'lines' => [['name' => 'ART', 'quantity' => 'QTE']],
            'services' => [[
                'serviceNumber' => 'PRESTA', 'sequence' => 'SEQ', 'requestedDate' => 'DATE',
                'serviceCode' => 'PRESTA_CODE', 'addressCode' => 'ADR',
                'recipient' => [
                    'firstName' => 'DEST_PRENOM', 'lastName' => 'DEST_NOM', 'company' => 'DEST_SOCIETE',
                    'email' => 'DEST_MAIL', 'phone' => 'DEST_TEL',
                    'addressLine1' => 'DEST_RUE', 'postalCode' => 'DEST_CP',
                    'city' => 'DEST_VILLE', 'country' => 'DEST_PAYS',
                ],
                'quantity' => 'SQTE', 'unit' => 'UNITE', 'requiredTimeMinutes' => 'DUREE',
                'remainingTimeMinutes' => 'DUREE', 'weight' => 'POIDS', 'volume' => 'VOLUME',
                'packageCount' => 'NBCOLIS', 'customerUnitPrice' => 'PU', 'customerTotalPrice' => 'PT',
                'providerUnitCost' => 'CU', 'providerTotalCost' => 'CT', 'status' => 'STATUT',
            ]],
        ];
    }
}
