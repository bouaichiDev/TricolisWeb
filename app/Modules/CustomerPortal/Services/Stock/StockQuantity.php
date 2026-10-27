<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Stock;

use App\Shared\Support\Money;

/**
 * Une quantité de stock telle que le portail la rend : chaîne décimale à
 * trois chiffres, jamais un flottant — la précision de `decimal(12,3)`,
 * calculée en bcmath comme `RecalculateStockBalance`.
 *
 * Une somme SQL sans ligne vaut `null` : un article sans balance a 0 en
 * stock, pas « rien ».
 */
final class StockQuantity
{
    public static function of(mixed $value): string
    {
        return Money::round($value === null ? '0' : (string) $value, 3);
    }

    /**
     * @return array{quantity: string, reservedQuantity: string, availableQuantity: string}
     */
    public static function totals(mixed $quantity, mixed $reserved, mixed $available): array
    {
        return [
            'quantity' => self::of($quantity),
            'reservedQuantity' => self::of($reserved),
            'availableQuantity' => self::of($available),
        ];
    }
}
