<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'une facture.
 *
 * Ni `customer_id`, ni `organization_id`, ni les trois totaux : changer de
 * client rendrait les lignes incohérentes avec leur commande d'origine, et les
 * totaux sont dérivés des lignes.
 */
final readonly class UpdateInvoiceData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'invoice_number' => 'invoiceNumber',
        'invoice_date' => 'invoiceDate',
        'period_from' => 'periodFrom',
        'period_to' => 'periodTo',
        'currency_code' => 'currencyCode',
        'external_reference' => 'externalReference',
        'remark' => 'remark',
        'status' => 'status',
    ];

    public function __construct(public PartialAttributes $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(PartialAttributes::fromValidated($validated, self::MAPPING));
    }
}
