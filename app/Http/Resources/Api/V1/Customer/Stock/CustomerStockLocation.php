<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Stock;

use App\Modules\Stock\Models\StockLocation;

/**
 * Un emplacement du dépôt, tel que le client le lit (§15) : de quoi retrouver
 * sa marchandise — dépôt, zone, allée, rack, niveau, code. Ni code-barres ni
 * identifiant d'ancien système, ni rien de ce que l'emplacement contient
 * pour d'autres clients : ce présentateur ne connaît que l'emplacement.
 */
final class CustomerStockLocation
{
    /**
     * @return array<string, string|null>|null
     */
    public static function present(?StockLocation $location): ?array
    {
        return $location === null ? null : [
            'id' => $location->id,
            'depot' => $location->depot?->name,
            'zoneCode' => $location->zone_code,
            'aisle' => $location->aisle,
            'rack' => $location->rack,
            'level' => $location->level,
            'locationCode' => $location->location_code,
        ];
    }
}
