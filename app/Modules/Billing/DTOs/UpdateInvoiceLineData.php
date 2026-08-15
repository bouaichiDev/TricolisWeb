<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'une ligne de facture.
 *
 * Les deux totaux sont absents de la table : toute modification de `quantity`,
 * `unitPrice`, `discountRate` ou `taxRate` déclenche leur recalcul dans
 * l'Action, puis celui des totaux de la facture.
 */
final readonly class UpdateInvoiceLineData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'order_service_id' => 'orderServiceId',
        'order_id' => 'orderId',
        'line_number' => 'lineNumber',
        'service_code' => 'serviceCode',
        'description' => 'description',
        'customer_order_reference' => 'customerOrderReference',
        'quantity' => 'quantity',
        'unit_price' => 'unitPrice',
        'discount_rate' => 'discountRate',
        'tax_rate' => 'taxRate',
        'service_completed_at' => 'serviceCompletedAt',
        'status' => 'status',
    ];

    /** Colonnes dont la modification impose un recalcul des totaux. */
    public const array RECALCULATES = ['quantity', 'unit_price', 'discount_rate', 'tax_rate'];

    public function __construct(public PartialAttributes $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(PartialAttributes::fromValidated($validated, self::MAPPING));
    }
}
