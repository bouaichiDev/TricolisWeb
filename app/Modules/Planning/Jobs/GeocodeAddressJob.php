<?php

declare(strict_types=1);

namespace App\Modules\Planning\Jobs;

use App\Modules\Addresses\Models\Address;
use App\Modules\Planning\Services\GeocodingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Donne ses coordonnées à une adresse, en file d'attente.
 *
 * **En file, et non pendant la requête.** Le service de géocodage est distant :
 * l'appeler dans le fil de l'enregistrement ferait attendre le formulaire le
 * temps d'un aller-retour, et un service lent ou muet empêcherait de créer une
 * adresse. Une adresse sans point reste utilisable partout sauf sur la carte.
 *
 * Le Job ne porte **que l'identifiant** : recharger l'adresse à l'exécution
 * garantit qu'il agit sur l'état courant, et non sur une copie sérialisée à la
 * mise en file — deux modifications rapprochées ne doivent pas faire gagner la
 * première.
 *
 * Une adresse supprimée entre-temps est ignorée sans erreur.
 */
class GeocodeAddressJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $addressId,
        private readonly string $organizationId,
        /** Vrai quand l'adresse a changé : les anciennes coordonnées sont fausses. */
        private readonly bool $force = false,
    ) {}

    public function handle(GeocodingService $geocoding): void
    {
        $address = Address::find($this->addressId);

        if ($address === null) {
            return;
        }

        $geocoding->locate($address, $this->organizationId, $this->force);
    }
}
