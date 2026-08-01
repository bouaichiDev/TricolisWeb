<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Shared\Support\Money;

/**
 * Calcule les deux totaux d'une ligne de facture.
 *
 * Le §11 interdit d'inventer une formule complexe et demande de chercher les
 * conventions existantes. **Aucune n'existe** : la Phase 2 enregistre
 * `customer_total_price` tel que fourni, sans le calculer. La formule minimale
 * du §11 est donc retenue, et c'est la seule :
 *
 * ```text
 * base              = quantity × unitPrice
 * totalExcludingTax = base × (1 − discountRate / 100)
 * totalIncludingTax = totalExcludingTax × (1 + taxRate / 100)
 * ```
 *
 * Chaque total est arrondi **avant** d'être sommé au niveau de la facture :
 * sommer des valeurs non arrondies puis arrondir produirait un écart d'un
 * centime avec la somme des lignes affichées. C'est la facture qui doit être
 * juste, pas le calcul intermédiaire.
 *
 * Les totaux ne sont jamais acceptés en entrée — le §11 l'exige.
 */
final readonly class CalculateInvoiceLineTotals
{
    /**
     * @return array{total_excluding_tax: string, total_including_tax: string}
     */
    public function execute(string $quantity, string $unitPrice, string $discountRate, string $taxRate): array
    {
        $base = Money::multiply($quantity, $unitPrice);
        $discount = Money::percentOf($base, $discountRate);
        $excludingTax = Money::subtract($base, $discount);
        $tax = Money::percentOf($excludingTax, $taxRate);

        return [
            'total_excluding_tax' => Money::round($excludingTax),
            'total_including_tax' => Money::round(Money::add($excludingTax, $tax)),
        ];
    }
}
