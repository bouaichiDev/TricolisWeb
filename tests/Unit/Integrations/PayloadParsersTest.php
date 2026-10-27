<?php

use App\Modules\Integrations\Services\Import\Parsers\JsonPayloadParser;
use App\Modules\Integrations\Services\Import\Parsers\PayloadParsers;
use App\Modules\Integrations\Services\Import\Parsers\XlsxPayloadParser;
use App\Modules\Integrations\Services\Import\Parsers\XmlPayloadParser;
use App\Modules\Integrations\Services\Import\RecordExtractor;

/**
 * Lecture des contenus clients (§12, §26, §56) : XML sans aucune résolution
 * d'entité, classeur réel, JSON invalide refusé.
 */
describe('XML', function (): void {
    it('rend les commandes d’une racine qui les enveloppe, attributs compris', function (): void {
        $parser = new XmlPayloadParser;
        $document = $parser->parse('<?xml version="1.0"?><Orders><Order id="7"><OrderNo>A</OrderNo></Order><Order id="8"><OrderNo>B</OrderNo></Order></Orders>');
        $records = (new RecordExtractor)->records($document, null, $parser->unwrapsRoot());

        expect($records)->toBe([['@id' => '7', 'OrderNo' => 'A'], ['@id' => '8', 'OrderNo' => 'B']]);
    });

    it('refuse une entité externe (XXE) sans jamais lire le fichier visé', function (): void {
        $xxe = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><Orders><Order><OrderNo>&x;</OrderNo></Order></Orders>';

        expect(fn () => (new XmlPayloadParser)->parse($xxe))->toThrow(RuntimeException::class, 'DTD');
    });

    it('refuse l’expansion d’entités et une DTD distante', function (string $xml): void {
        expect(fn () => (new XmlPayloadParser)->parse($xml))->toThrow(RuntimeException::class);
    })->with([
        'billion laughs' => ['<?xml version="1.0"?><!DOCTYPE l [<!ENTITY a "aaaa"><!ENTITY b "&a;&a;&a;">]><r>&b;</r>'],
        'dtd réseau' => ['<?xml version="1.0"?><!DOCTYPE r SYSTEM "http://169.254.169.254/latest/meta-data"><r/>'],
    ]);

    it('refuse un XML mal formé ou trop profond', function (): void {
        expect(fn () => (new XmlPayloadParser)->parse('<Orders><Order></Orders>'))->toThrow(RuntimeException::class, 'bien formé')
            ->and(fn () => (new XmlPayloadParser(3))->parse('<a><b><c><d>x</d></c></b></a>'))->toThrow(RuntimeException::class, 'profondément');
    });
});

describe('JSON', function (): void {
    it('refuse un JSON invalide et rend l’enveloppe au chemin des enregistrements', function (): void {
        expect(fn () => (new JsonPayloadParser)->parse('{"data": ['))->toThrow(RuntimeException::class, 'JSON valide');

        $records = (new RecordExtractor)->records((new JsonPayloadParser)->parse('{"data":[{"reference":"S-1"}],"next":null}'), 'data', false);

        expect($records)->toBe([['reference' => 'S-1']]);
    });

    it('signale un chemin d’enregistrements absent', function (): void {
        expect(fn () => (new RecordExtractor)->records(['items' => []], 'data', false))->toThrow(RuntimeException::class, 'absent');
    });
});

describe('Excel', function (): void {
    it('lit un vrai classeur : en-tête, dates Excel, décimaux, cellules vides', function (): void {
        $rows = (new XlsxPayloadParser)->parse((string) file_get_contents(__DIR__.'/../../Fixtures/integrations/orders.xlsx'));

        expect($rows)->toHaveCount(3)
            ->and($rows[0]['Commande'])->toBe('XL-1001')
            ->and($rows[0]['Date'])->toBe('2026-10-05')
            ->and($rows[1]['Quantité'])->toBe('4.5')
            ->and($rows[2]['Quantité'])->toBeNull();
    });

    it('refuse un fichier qui n’est pas un classeur, et un classeur trop long', function (): void {
        expect(fn () => (new XlsxPayloadParser)->parse('PK pas un classeur'))->toThrow(RuntimeException::class)
            ->and(fn () => (new XlsxPayloadParser(2))->parse((string) file_get_contents(__DIR__.'/../../Fixtures/integrations/orders.xlsx')))->toThrow(RuntimeException::class, '2 lignes');
    });

    it('reconnaît les formats avec tolérance et refuse les autres en les nommant', function (): void {
        expect(PayloadParsers::kind('Excel'))->toBe('xlsx')
            ->and(PayloadParsers::kind('text/csv'))->toBe('csv')
            ->and(PayloadParsers::kind('edifact'))->toBeNull()
            ->and(fn () => (new PayloadParsers)->for('edifact'))->toThrow(RuntimeException::class, 'edifact');
    });
});
