<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Orders\Models\OrderService;

/**
 * La distance entre le dépôt d'où part la commande et l'adresse de la prestation.
 *
 * **À vol d'oiseau, et il faut le savoir.** La formule de Haversine donne la
 * distance orthodromique, pas la route : en Suisse, un col ou un lac peut
 * doubler l'écart. Le choix tient à ce qu'un tarif se calcule à chaque ligne de
 * facture et sur chaque page de préfacturation — interroger le service de
 * routage y coûterait un appel réseau par prestation, et épuiserait le quota du
 * fournisseur GPS pour afficher vingt-cinq lignes.
 *
 * Une distance routière exigerait de la **stocker** au moment où la prestation
 * est planifiée, quand l'itinéraire est déjà calculé. C'est une décision de
 * conception, pas un réglage : tant qu'elle n'est pas prise, la distance reste
 * orthodromique, et les barèmes qui l'emploient s'écrivent en connaissance de
 * cause.
 *
 * Sans coordonnées des deux côtés, la valeur reste **nulle** : une formule qui
 * la nomme échoue clairement plutôt que de facturer sur une distance inventée.
 */
final readonly class DepotDistance
{
    /** Rayon moyen de la Terre, en kilomètres. */
    private const float EARTH_RADIUS_KM = 6371.0;

    /** La distance en kilomètres, ou null si l'un des points manque. */
    public function kilometres(OrderService $service): ?string
    {
        $from = $this->depotAddress($service);
        $to = $service->address;

        if ($from === null || $to === null) {
            return null;
        }

        if (! $this->located($from) || ! $this->located($to)) {
            return null;
        }

        return number_format($this->between($from, $to), 3, '.', '');
    }

    private function depotAddress(OrderService $service): ?Address
    {
        $depotId = $service->order?->depot_id;

        if ($depotId === null) {
            return null;
        }

        return EntityAddress::where('entity_type', 'depot')
            ->where('entity_id', $depotId)
            ->with('address')
            ->first()?->address;
    }

    private function located(Address $address): bool
    {
        return $address->latitude !== null
            && $address->longitude !== null
            // Le point (0,0) est en plein golfe de Guinee : c'est une adresse
            // non geocodee, pas un lieu de livraison.
            && ! ((float) $address->latitude === 0.0 && (float) $address->longitude === 0.0);
    }

    private function between(Address $from, Address $to): float
    {
        $fromLat = deg2rad((float) $from->latitude);
        $toLat = deg2rad((float) $to->latitude);
        $deltaLat = $toLat - $fromLat;
        $deltaLon = deg2rad((float) $to->longitude - (float) $from->longitude);

        $a = sin($deltaLat / 2) ** 2 + cos($fromLat) * cos($toLat) * sin($deltaLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
