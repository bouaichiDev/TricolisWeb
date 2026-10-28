<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Tours\Models\TourStopService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Ce qu'un fournisseur « porte » réellement sur un arrêt : un service
 * **acquis**, c'est-à-dire affecté (`is_active_assignment`) et confirmé
 * (`confirmed_at`).
 *
 * Les deux conditions comptent. Une composition en cours chez le planificateur
 * n'est pas encore confirmée ; un service retiré d'une tournée déjà partie
 * garde sa ligne — avec `confirmed_at` — mais n'est plus actif
 * (`UnplanOrderServices::release`). Sans le second filtre, le fournisseur
 * garderait les documents et la saisie de preuve d'un service replanifié chez
 * un autre. Une seule définition, pour l'affichage, les documents et les
 * preuves.
 */
final class ProviderAcquiredServices
{
    /**
     * @return Builder<TourStopService>
     */
    public static function lines(): Builder
    {
        return TourStopService::query()->where('is_active_assignment', true)->whereNotNull('confirmed_at');
    }

    public static function isAcquired(TourStopService $line): bool
    {
        return (bool) $line->is_active_assignment && $line->confirmed_at !== null;
    }

    /**
     * Les preuves visibles du fournisseur : celles dont le couple arrêt +
     * service correspond à un service acquis sur cet arrêt. Une preuve saisie
     * au back-office pour un autre client, ou sur un service non acquis, reste
     * invisible — avec sa signature et sa photo.
     *
     * @return Builder<ProofOfDelivery>
     */
    public static function proofs(): Builder
    {
        return ProofOfDelivery::query()->whereExists(static fn (QueryBuilder $line) => $line
            ->selectRaw('1')
            ->from('tour_stop_services')
            ->whereColumn('tour_stop_services.tour_stop_id', 'proofs_of_delivery.tour_stop_id')
            ->whereColumn('tour_stop_services.order_service_id', 'proofs_of_delivery.order_service_id')
            ->where('tour_stop_services.is_active_assignment', true)
            ->whereNotNull('tour_stop_services.confirmed_at'));
    }
}
