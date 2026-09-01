<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Models\InvoiceLineAddressSnapshot;
use App\Modules\Exports\DTOs\InvoiceExportData;
use App\Modules\Exports\Services\ExportFieldMapping;
use App\Modules\Exports\Services\Formats\InvoiceCsvFormatter;
use App\Modules\Exports\Services\Formats\InvoiceJsonFormatter;
use App\Modules\Exports\Services\Formats\InvoicePdfFormatter;
use App\Modules\Exports\Services\Formats\InvoiceXmlFormatter;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * Le fichier tel que le client le reçoit.
 *
 * Ces cas ne touchent pas la base : la forme canonique se construit depuis des
 * modèles en mémoire, ce qui laisse voir le rendu sans le bruit d'un jeu de
 * données.
 *
 * Le conteneur reste nécessaire : un modèle Laravel a besoin de l'application
 * pour caster ses dates, même sans jamais interroger la base.
 */
uses(TestCase::class);

beforeEach(function (): void {
    $this->mapping = new ExportFieldMapping;
    $this->json = new InvoiceJsonFormatter($this->mapping);
    $this->xml = new InvoiceXmlFormatter($this->mapping);
    $this->csv = new InvoiceCsvFormatter($this->mapping);
    $this->pdf = new InvoicePdfFormatter;

    $this->data = function (array $invoice = [], array $line = []): InvoiceExportData {
        $model = new Invoice(array_merge([
            'invoice_number' => 'INV-2026-00042',
            'invoice_date' => '2026-08-31',
            'currency_code' => 'CHF',
            'subtotal' => '100',
            'tax_total' => '8.1',
            'total' => '108.1',
        ], $invoice));

        $row = new InvoiceLine(array_merge([
            'line_number' => 1,
            'service_code' => 'DEL',
            'description' => 'Livraison Genève & retour <urgent>',
            'quantity' => '2',
            'unit_price' => '50',
            'total_excluding_tax' => '100',
            'total_including_tax' => '108.1',
        ], $line));

        $row->setRelation('addressSnapshot', new InvoiceLineAddressSnapshot([
            'name' => 'Migros & Cie',
            'address_line1' => 'Rue du Rhône 12',
            'postal_code' => '1204',
            'city' => 'Genève',
            'country' => 'CH',
        ]));

        $model->setRelation('lines', new Collection([$row]));

        return InvoiceExportData::from($model);
    };
});

describe('JSON', function (): void {
    it('rend les montants en chaînes à deux décimales', function (): void {
        $payload = json_decode($this->json->render(($this->data)(), [], 'UTF-8'), true);

        expect($payload['total'])->toBe('108.10')
            ->and($payload['subtotal'])->toBe('100.00')
            ->and($payload['lines'][0]['unitPrice'])->toBe('50.00')
            // Une quantite se dit au millieme : un demi-palette existe.
            ->and($payload['lines'][0]['quantity'])->toBe('2.000');
    });

    /** §13 : la facture d'août garde l'adresse d'août. */
    it('reprend l’adresse du cliché', function (): void {
        $payload = json_decode($this->json->render(($this->data)(), [], 'UTF-8'), true);

        expect($payload['lines'][0]['address']['city'])->toBe('Genève');
    });

    /** §66 : le mapping renomme, il n'évalue rien. */
    it('renomme selon le vocabulaire du client', function (): void {
        $settings = [
            'fieldMapping' => ['invoiceNumber' => 'numero', 'lines' => 'postes', 'lines.serviceCode' => 'code'],
            'staticValues' => ['source' => 'TRICOLIS'],
        ];

        $payload = json_decode($this->json->render(($this->data)(), $settings, 'UTF-8'), true);

        expect($payload)->toHaveKey('numero')
            ->and($payload)->not->toHaveKey('invoiceNumber')
            ->and($payload['postes'][0]['code'])->toBe('DEL')
            ->and($payload['source'])->toBe('TRICOLIS');
    });

    /** §67 : la liste blanche tient — un chemin inventé ne sert à rien. */
    it('ignore un champ que la facture ne connaît pas', function (): void {
        $settings = ['fieldMapping' => ['organizationId' => 'org', 'lines.costPrice' => 'achat']];

        $payload = json_decode($this->json->render(($this->data)(), $settings, 'UTF-8'), true);

        expect($payload)->not->toHaveKey('org')
            ->and($payload['lines'][0])->not->toHaveKey('achat');
    });

    /** Les identifiants internes ne regardent pas le client (§64). */
    it('n’expose aucun identifiant interne', function (): void {
        $rendered = $this->json->render(($this->data)(), [], 'UTF-8');

        expect($rendered)->not->toContain('"id"')
            ->and($rendered)->not->toContain('customer_id')
            ->and($rendered)->not->toContain('organization_id');
    });
});

describe('XML', function (): void {
    /** §83 : une esperluette dans une raison sociale ne casse pas le document. */
    it('échappe ce qui casserait le document', function (): void {
        $rendered = $this->xml->render(($this->data)(), [], 'UTF-8');

        expect($rendered)->toContain('Livraison Genève &amp; retour &lt;urgent&gt;');

        $document = new DOMDocument;

        expect($document->loadXML($rendered))->toBeTrue();
    });

    it('nomme la racine et les lignes selon la configuration', function (): void {
        $settings = ['rootName' => 'Facture', 'lineNodeName' => 'Poste'];

        $rendered = $this->xml->render(($this->data)(), $settings, 'UTF-8');

        expect($rendered)->toContain('<Facture>')->toContain('<Poste>');
    });

    /** Un nom de balise inutilisable produirait un document rejeté à l'arrivée. */
    it('retombe sur un nom valable si la configuration en propose un mauvais', function (): void {
        $rendered = $this->xml->render(($this->data)(), ['rootName' => '3 factures !'], 'UTF-8');

        $document = new DOMDocument;

        expect($document->loadXML($rendered))->toBeTrue()
            ->and($document->documentElement->nodeName)->toBe('invoice');
    });

    it('déclare l’encodage demandé', function (): void {
        expect($this->xml->render(($this->data)(), [], 'ISO-8859-1'))
            ->toContain('encoding="ISO-8859-1"');
    });
});

describe('CSV', function (): void {
    /** Un CSV n'a pas de hierarchie : l'en-tete de facture suit chaque ligne. */
    it('répète l’en-tête de facture sur chaque ligne', function (): void {
        $rendered = $this->csv->render(($this->data)(), [], 'UTF-8');
        $rows = array_values(array_filter(explode('
', trim($rendered))));

        expect($rows)->toHaveCount(2)
            ->and($rows[0])->toContain('invoiceNumber')
            ->and($rows[0])->toContain('lineNumber')
            ->and($rows[1])->toContain('INV-2026-00042');
    });

    it('aplatit l’adresse en colonnes', function (): void {
        $rendered = $this->csv->render(($this->data)(), [], 'UTF-8');

        expect($rendered)->toContain('address.city')->toContain('Genève');
    });

    /** Le point-virgule est la norme francophone ; la virgule ailleurs. */
    it('suit le séparateur demandé', function (): void {
        $rendered = $this->csv->render(($this->data)(), ['delimiter' => ','], 'UTF-8');

        expect($rendered)->toContain('invoiceNumber,invoiceDate');
    });

    /** Une raison sociale contenant le separateur ne doit pas decaler la colonne. */
    it('encadre une valeur qui contient le séparateur', function (): void {
        $data = ($this->data)([], ['description' => 'Livraison; retour']);

        $rendered = $this->csv->render($data, [], 'UTF-8');
        $rows = array_values(array_filter(explode('
', trim($rendered))));

        expect($rows[1])->toContain('"Livraison; retour"')
            ->and(str_getcsv($rows[1], ';', '"', ''))->toHaveCount(count(str_getcsv($rows[0], ';', '"', '')));
    });

    it('renomme les colonnes selon le mapping du client', function (): void {
        $settings = ['fieldMapping' => ['invoiceNumber' => 'numero']];

        expect($this->csv->render(($this->data)(), $settings, 'UTF-8'))
            ->toContain('numero')
            ->not->toContain('invoiceNumber');
    });
});

describe('PDF', function (): void {
    it('produit un document PDF', function (): void {
        $rendered = $this->pdf->render(($this->data)(), [], 'UTF-8');

        expect($rendered)->toStartWith('%PDF-')
            ->and($this->pdf->contentType())->toBe('application/pdf')
            ->and($this->pdf->extension())->toBe('pdf');
    });

    /** Le titre est configurable ; le reste de la mise en page ne l'est pas. */
    it('accepte un titre de document', function (): void {
        $rendered = $this->pdf->render(($this->data)(), ['documentTitle' => 'Note de frais'], 'UTF-8');

        expect($rendered)->toStartWith('%PDF-');
    });
});
