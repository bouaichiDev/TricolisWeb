<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Formats;

use App\Modules\Exports\DTOs\InvoiceExportData;
use App\Modules\Exports\Services\ExportFieldMapping;
use DOMDocument;
use DOMElement;

/**
 * La facture en XML.
 *
 * **Construit par DOM, jamais par concaténation.** Le §83 l'exige, et pour une
 * raison concrète : une raison sociale contenant `&` ou `<` casserait un
 * document assemblé à la main, et la facture arriverait illisible chez le
 * client. `DOMDocument` échappe tout, y compris ce à quoi on n'a pas pensé.
 *
 * Les noms de la racine et des lignes viennent du mapping — `rootName`,
 * `lineNodeName` — car chaque destinataire attend son propre vocabulaire.
 */
final readonly class InvoiceXmlFormatter implements InvoiceFormatter
{
    private const string DEFAULT_ROOT = 'invoice';

    private const string DEFAULT_LINE = 'line';

    public function __construct(private ExportFieldMapping $mapping) {}

    public function render(InvoiceExportData $invoice, array $settings, string $encoding): string
    {
        $payload = $this->mapping->apply($invoice, $settings);

        $document = new DOMDocument('1.0', $encoding === '' ? 'UTF-8' : $encoding);
        $document->formatOutput = true;

        $root = $document->createElement($this->tag($settings['rootName'] ?? null, self::DEFAULT_ROOT));
        $document->appendChild($root);

        $lineTag = $this->tag($settings['lineNodeName'] ?? null, self::DEFAULT_LINE);

        foreach ($payload as $name => $value) {
            if (is_array($value) && $this->isList($value)) {
                $collection = $document->createElement($this->tag($name, 'items'));

                foreach ($value as $entry) {
                    $node = $document->createElement($lineTag);
                    $this->fill($document, $node, is_array($entry) ? $entry : ['value' => $entry]);
                    $collection->appendChild($node);
                }

                $root->appendChild($collection);

                continue;
            }

            $this->append($document, $root, (string) $name, $value);
        }

        return (string) $document->saveXML();
    }

    public function extension(): string
    {
        return 'xml';
    }

    public function contentType(): string
    {
        return 'application/xml';
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function fill(DOMDocument $document, DOMElement $parent, array $values): void
    {
        foreach ($values as $name => $value) {
            if (is_array($value)) {
                $child = $document->createElement($this->tag($name, 'group'));
                $this->fill($document, $child, $value);
                $parent->appendChild($child);

                continue;
            }

            $this->append($document, $parent, (string) $name, $value);
        }
    }

    private function append(DOMDocument $document, DOMElement $parent, string $name, mixed $value): void
    {
        $node = $document->createElement($this->tag($name, 'field'));
        // `createTextNode` echappe ; passer la valeur au constructeur ne le
        // ferait pas, et une esperluette suffirait a rompre le document.
        $node->appendChild($document->createTextNode($value === null ? '' : (string) $value));
        $parent->appendChild($node);
    }

    /**
     * Un nom de balise sûr.
     *
     * Un mapping client peut proposer n'importe quoi — un espace, un chiffre en
     * tête, un accent. Ce qui n'est pas un nom XML valide est remplacé plutôt
     * que de produire un document que le destinataire rejettera.
     */
    private function tag(mixed $name, string $fallback): string
    {
        $candidate = is_string($name) ? trim($name) : '';
        $clean = preg_replace('/[^A-Za-z0-9_.-]/', '', $candidate) ?? '';

        return preg_match('/^[A-Za-z_]/', $clean) === 1 ? $clean : $fallback;
    }

    /** @param array<mixed> $value */
    private function isList(array $value): bool
    {
        return $value === [] || array_is_list($value);
    }
}
