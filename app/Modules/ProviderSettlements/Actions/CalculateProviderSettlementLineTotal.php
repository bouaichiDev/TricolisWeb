<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Actions;

use App\Shared\Support\Money;

/**
 * Calcule le total d'une ligne de décompte fournisseur.
 *
 * ```text
 * totalCost = quantity × unitCost
 * ```
 *
 * Le §19 est explicite : ni taxe ni remise au niveau ligne. Le modèle ne porte
 * d'ailleurs aucun champ pour les accueillir.
 */
final readonly class CalculateProviderSettlementLineTotal
{
    public function execute(string $quantity, string $unitCost): string
    {
        return Money::round(Money::multiply($quantity, $unitCost));
    }
}
