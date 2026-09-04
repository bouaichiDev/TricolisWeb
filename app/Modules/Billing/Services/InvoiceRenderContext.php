<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Exports\DTOs\InvoiceExportData;
use App\Modules\Organizations\Services\OrganizationLogo;

/**
 * Les données qu'un modèle de facture a le droit de nommer.
 *
 * **Une liste close, construite à la main.** Le §0.12 interdit de donner
 * l'objet Eloquent au moteur : un modèle pouvant écrire
 * `{{ customer.apiKey }}` ferait fuiter par le document ce que l'API protège.
 * Rien n'est lu par réflexion ici — chaque clé est écrite, une par une.
 *
 * `invoice` et `invoice.lines` viennent d'`InvoiceExportData`, la forme
 * canonique déjà employée par les exports JSON et XML. Les montants y sont des
 * chaînes à décimales fixes : un flottant se relirait `108.10000000000001` sur
 * la facture du client.
 *
 * L'adresse de chaque ligne vient du **cliché** pris à la création de la ligne,
 * jamais de l'adresse vivante : une facture d'août affiche l'adresse d'août,
 * même si le client a déménagé depuis.
 *
 * `organization.logo` fait exception à la règle des chaînes courtes : c'est le
 * fichier entier, encodé. Il s'écrit `<img src="{{ organization.logo }}">` dans
 * le modèle, et le document se suffit alors à lui-même.
 */
final readonly class InvoiceRenderContext
{
    public function __construct(private OrganizationLogo $logo) {}

    /**
     * Clés d'une ligne, toujours toutes présentes.
     *
     * `InvoiceExportData` élague les valeurs nulles — un export JSON n'a pas
     * besoin d'envoyer `"remark": null`. Un modèle, si : une référence déclarée
     * mais absente du contexte fait échouer le rendu, et une facture sans
     * remarque n'est pas une facture cassée.
     *
     * @var list<string>
     */
    private const array LINE_KEYS = [
        'lineNumber', 'serviceCode', 'description', 'customerOrderReference',
        'quantity', 'unitPrice', 'discountRate', 'taxRate',
        'totalExcludingTax', 'totalIncludingTax', 'serviceCompletedAt',
    ];

    /** @var list<string> */
    private const array ADDRESS_KEYS = [
        'addressCode', 'name', 'addressLine1', 'addressLine2', 'postalCode', 'city', 'country',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer', 'organization', 'lines.addressSnapshot']);

        $data = InvoiceExportData::from($invoice);
        $customer = $invoice->customer;
        $organization = $invoice->organization;

        return [
            'invoice' => [
                'invoiceNumber' => $data->invoiceNumber,
                'invoiceDate' => $data->invoiceDate,
                'periodFrom' => $data->periodFrom,
                'periodTo' => $data->periodTo,
                'currencyCode' => $data->currencyCode,
                'subtotal' => $data->subtotal,
                'taxTotal' => $data->taxTotal,
                'total' => $data->total,
                'externalReference' => $data->externalReference,
                'remark' => $data->remark,
                'lines' => array_map([self::class, 'line'], $data->lines),
            ],
            'customer' => [
                'code' => $customer?->code,
                'name' => $customer?->name,
                'email' => $customer?->email,
                'phone' => $customer?->phone,
                'legalName' => $customer?->legal_name,
            ],
            'organization' => [
                'code' => $organization?->code,
                'name' => $organization?->name,
                'email' => $organization?->email,
                'phone' => $organization?->phone,
                // Le logo part **encodé dans le document**, pas en lien. dompdf
                // va chercher chaque ressource externe au moment du rendu : une
                // URL le ferait dépendre d'un serveur joignable au bon moment,
                // et d'une session qu'il n'a pas. `null` quand il n'y en a pas
                // — une image manquante fait un trou, un `src` vide casse la
                // mise en page.
                'logo' => $this->logo->dataUri($organization),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private static function line(array $line): array
    {
        $normalized = [];

        foreach (self::LINE_KEYS as $key) {
            $normalized[$key] = $line[$key] ?? null;
        }

        $address = is_array($line['address'] ?? null) ? $line['address'] : [];
        $normalized['address'] = [];

        foreach (self::ADDRESS_KEYS as $key) {
            $normalized['address'][$key] = $address[$key] ?? null;
        }

        return $normalized;
    }

    /**
     * Chemins proposés à l'éditeur de modèle.
     *
     * Le §21 veut que l'utilisateur insère une variable sans en mémoriser la
     * syntaxe ; cette liste est ce que l'écran lui offre, et ce que le rendu
     * saura résoudre.
     *
     * @return list<string>
     */
    public static function availablePaths(): array
    {
        $scalars = [
            'invoice.invoiceNumber', 'invoice.invoiceDate', 'invoice.periodFrom', 'invoice.periodTo',
            'invoice.currencyCode', 'invoice.subtotal', 'invoice.taxTotal', 'invoice.total',
            'invoice.externalReference', 'invoice.remark',
            'customer.code', 'customer.name', 'customer.email', 'customer.phone', 'customer.legalName',
            'organization.code', 'organization.name', 'organization.email', 'organization.phone',
            'organization.logo',
        ];

        $lines = [
            'invoice.lines',
            'invoice.lines.lineNumber', 'invoice.lines.serviceCode', 'invoice.lines.description',
            'invoice.lines.customerOrderReference', 'invoice.lines.quantity', 'invoice.lines.unitPrice',
            'invoice.lines.discountRate', 'invoice.lines.taxRate',
            'invoice.lines.totalExcludingTax', 'invoice.lines.totalIncludingTax',
            'invoice.lines.serviceCompletedAt',
            'invoice.lines.address.name', 'invoice.lines.address.addressLine1',
            'invoice.lines.address.postalCode', 'invoice.lines.address.city', 'invoice.lines.address.country',
        ];

        return [...$scalars, ...$lines];
    }
}
