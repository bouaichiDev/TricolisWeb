<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les documents d'exécution qu'un fournisseur peut ouvrir.
 *
 * Connaître un identifiant ne suffit jamais : un document n'est accessible que
 * par un chemin métier jusqu'à une tournée **visible du fournisseur courant**
 * (ProviderTourScope), dans l'organisation de ce fournisseur :
 *
 * - `DocumentLink → Tour` ou `→ TourStop` de la tournée ;
 * - `DocumentLink → OrderService` **confirmé** sur un arrêt de la tournée ;
 * - `DocumentLink → ProofOfDelivery` d'un arrêt de la tournée, ou signature et
 *   photo référencées par cette preuve.
 *
 * Jamais un document de la seule commande (facture, bon d'autres services) :
 * la commande appartient au client, pas au transporteur. Sans chemin, le
 * document répond 404, comme s'il n'existait pas.
 */
final readonly class ProviderDocumentAccess
{
    public function __construct(private ProviderTourScope $scope, private CurrentProviderContext $context) {}

    /**
     * @param  string|null  $tourId  restreint à une tournée (déjà vérifiée par l'appelant)
     * @return Builder<Document>
     */
    public function query(?string $tourId = null): Builder
    {
        $tours = $tourId !== null ? [$tourId] : $this->scope->tours()->select('tours.id');
        $stops = TourStop::query()->select('id')->whereIn('tour_id', $tours);
        $services = TourStopService::query()->select('order_service_id')->whereIn('tour_stop_id', $stops)->whereNotNull('confirmed_at');
        $proofs = ProofOfDelivery::query()->whereIn('tour_stop_id', $stops);
        $linked = static fn (string $alias, $ids) => static fn (Builder $link) => $link->where('entity_type', $alias)->whereIn('entity_id', $ids);

        return Document::query()
            ->where('organization_id', $this->context->organizationId())
            ->where(static function (Builder $paths) use ($tours, $stops, $services, $proofs, $linked): void {
                $paths->whereHas('links', $linked(MorphMap::TOUR, $tours))
                    ->orWhereHas('links', $linked(MorphMap::TOUR_STOP, $stops))
                    ->orWhereHas('links', $linked(MorphMap::ORDER_SERVICE, $services))
                    ->orWhereHas('links', $linked(MorphMap::PROOF_OF_DELIVERY, (clone $proofs)->select('id')))
                    ->orWhereIn('id', (clone $proofs)->select('signature_document_id')->whereNotNull('signature_document_id'))
                    ->orWhereIn('id', (clone $proofs)->select('photo_document_id')->whereNotNull('photo_document_id'));
            });
    }

    public function find(string $id): Document
    {
        return $this->query()->whereKey($id)->firstOr(static fn () => abort(404, 'Document introuvable.'));
    }
}
