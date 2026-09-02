<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Tours\Models\Tour;
use Illuminate\Support\Facades\DB;

/**
 * L'adresse du dépôt d'une tournée.
 *
 * Deux décisions en dépendent, et elles doivent lire la même chose : quel arrêt
 * remonte en tête — celui qui partage cette adresse — et où se pose un
 * chargement créé automatiquement. Deux requêtes séparées finiraient par
 * diverger sur les liaisons retenues, et le chargement se retrouverait sur un
 * arrêt que rien ne promeut.
 */
final readonly class DepotAddress
{
    /** Nulle quand la tournée n'a pas de dépôt, ou qu'il n'a pas d'adresse. */
    public function for(Tour $tour): ?string
    {
        if ($tour->depot_id === null) {
            return null;
        }

        return DB::table('entity_addresses')
            ->where('entity_type', 'depot')
            ->where('entity_id', $tour->depot_id)
            // L'adresse par defaut d'abord, l'identifiant pour departager :
            // c'est l'ordre que la promotion de l'arret de depot appliquait
            // deja, et les deux doivent designer la meme.
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('address_id');
    }
}
