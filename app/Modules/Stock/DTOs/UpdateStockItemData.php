<?php

declare(strict_types=1);

namespace App\Modules\Stock\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'un article de stock.
 *
 * `customer_id` n'y figure pas : transférer un article d'un client à l'autre
 * emporterait ses soldes, mouvements et réservations hors de leur périmètre.
 */
final readonly class UpdateStockItemData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'catalog_item_id' => 'catalogItemId',
        'article_code' => 'articleCode',
        'barcode' => 'barcode',
        'description' => 'description',
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
