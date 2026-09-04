<?php

declare(strict_types=1);

namespace App\Modules\Planning\Actions;

use App\Modules\Addresses\Models\Address;
use App\Modules\Planning\Jobs\GeocodeAddressJob;

/**
 * Met en file le géocodage des adresses qui n'ont pas de point.
 *
 * Une commande dont l'adresse n'est pas située reste planifiable — le §74
 * l'exige — mais elle n'apparaît pas sur la carte, et son itinéraire ne peut
 * pas être calculé. C'est la raison de ce rattrapage : le §83 demande de
 * géocoder « uniquement les adresses nécessaires à l'opération », pas le
 * catalogue entier.
 *
 * **Rien n'est appelé ici.** L'Action ne fait que décider quelles adresses en
 * ont besoin ; le travail distant se fait en file, après la validation de la
 * transaction — sans quoi le Job chercherait une adresse pas encore écrite.
 */
final readonly class GeocodeMissingAddresses
{
    /**
     * @param  list<string|null>  $addressIds  doublons et nuls tolérés
     * @return int nombre d'adresses mises en file
     */
    public function execute(array $addressIds, string $organizationId): int
    {
        $wanted = array_values(array_unique(array_filter(
            $addressIds,
            static fn (?string $id): bool => $id !== null && $id !== '',
        )));

        if ($wanted === []) {
            return 0;
        }

        $missing = Address::whereIn('id', $wanted)
            ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
            ->pluck('id');

        foreach ($missing as $id) {
            GeocodeAddressJob::dispatch($id, $organizationId)->afterCommit();
        }

        return $missing->count();
    }
}
