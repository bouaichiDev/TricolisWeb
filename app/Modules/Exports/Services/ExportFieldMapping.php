<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services;

use App\Modules\Exports\DTOs\InvoiceExportData;

/**
 * Traduit la facture canonique dans le vocabulaire d'un client.
 *
 * **Déclaratif, et rien d'autre.** Le §66 interdit toute évaluation — pas
 * d'expression PHP, pas de JavaScript, pas de commande. Le mapping ne fait que
 * renommer des champs connus et poser des valeurs fixes ; ce qu'il ne sait pas
 * nommer, il l'ignore.
 *
 * ```json
 * {
 *   "fieldMapping": { "invoiceNumber": "numero", "lines.serviceCode": "code" },
 *   "staticValues": { "source": "TRICOLIS" }
 * }
 * ```
 *
 * Sans mapping, la forme canonique part telle quelle : c'est le cas le plus
 * courant, et l'absence de configuration ne doit pas empêcher un envoi.
 */
final readonly class ExportFieldMapping
{
    /**
     * Champs autorisés de l'en-tête.
     *
     * Le §67 demande une liste blanche : un chemin arbitraire vers le modèle
     * Laravel laisserait un client se servir dans la base.
     *
     * @var list<string>
     */
    private const array INVOICE_FIELDS = [
        'invoiceNumber', 'invoiceDate', 'periodFrom', 'periodTo', 'currencyCode',
        'subtotal', 'taxTotal', 'total', 'externalReference', 'remark',
    ];

    /** @var list<string> */
    private const array LINE_FIELDS = [
        'lineNumber', 'serviceCode', 'description', 'customerOrderReference',
        'quantity', 'unitPrice', 'discountRate', 'taxRate',
        'totalExcludingTax', 'totalIncludingTax', 'serviceCompletedAt',
    ];

    /** @var list<string> */
    private const array ADDRESS_FIELDS = [
        'addressCode', 'name', 'addressLine1', 'addressLine2',
        'postalCode', 'city', 'country',
    ];

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function apply(InvoiceExportData $invoice, array $settings): array
    {
        $map = is_array($settings['fieldMapping'] ?? null) ? $settings['fieldMapping'] : [];
        $statics = is_array($settings['staticValues'] ?? null) ? $settings['staticValues'] : [];

        $source = $invoice->toArray();
        $payload = [];

        foreach (self::INVOICE_FIELDS as $field) {
            if (! array_key_exists($field, $source)) {
                continue;
            }

            $payload[$this->nameOf($map, $field, $field)] = $source[$field];
        }

        $linesKey = $this->nameOf($map, 'lines', 'lines');
        $payload[$linesKey] = array_map(
            fn (array $line): array => $this->line($line, $map),
            $invoice->lines,
        );

        // Les valeurs fixes en dernier : c'est une surcharge deliberee du
        // client, elle doit primer sur ce que la facture a produit.
        foreach ($statics as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private function line(array $line, array $map): array
    {
        $mapped = [];

        foreach (self::LINE_FIELDS as $field) {
            if (array_key_exists($field, $line)) {
                $mapped[$this->nameOf($map, "lines.$field", $field)] = $line[$field];
            }
        }

        if (is_array($line['address'] ?? null)) {
            $address = [];

            foreach (self::ADDRESS_FIELDS as $field) {
                if (array_key_exists($field, $line['address'])) {
                    $address[$this->nameOf($map, "address.$field", $field)] = $line['address'][$field];
                }
            }

            $mapped[$this->nameOf($map, 'lines.address', 'address')] = $address;
        }

        return $mapped;
    }

    /**
     * Le nom demandé par le client, ou celui d'origine.
     *
     * Une valeur qui n'est pas une chaîne non vide est ignorée : un mapping mal
     * saisi ne doit pas produire une clé vide dans le fichier livré.
     *
     * @param  array<string, mixed>  $map
     */
    private function nameOf(array $map, string $path, string $fallback): string
    {
        $name = $map[$path] ?? null;

        return is_string($name) && trim($name) !== '' ? trim($name) : $fallback;
    }
}
