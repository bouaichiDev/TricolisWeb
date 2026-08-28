<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

use App\Modules\Planning\Services\ConfirmedContent;
use App\Modules\Tours\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tournée vue depuis une liste : ni période ni affectation chargée, seulement
 * leurs compteurs.
 *
 * `plannedBy` nomme celui qui réserve le brouillon. Il n'est pas une colonne :
 * le §23 l'interdit, et il se lit dans le journal d'audit — résolu en une
 * requête pour toute la page, jamais tournée par tournée.
 *
 * Les arrêts font exception, et seulement sur demande — `?withStops=1`. La vue
 * en colonnes les montre sous chaque tournée ; les charger toujours coûterait
 * une jointure à qui ne veut qu'une liste.
 *
 * @mixin Tour
 */
class TourListResource extends JsonResource
{
    /**
     * @param  array<string, array{id: string, name: string}>  $planners  créateurs
     * @param  array<string, array{id: string, name: string}>  $holders  réservations en cours
     */
    public function __construct(
        $resource,
        private readonly array $planners = [],
        private readonly array $holders = [],
        /** Montrer la composition en cours — seulement à celui qui la mène. */
        private readonly bool $includePending = false,
    ) {
        parent::__construct($resource);
    }

    /**
     * Les arrêts visibles, ceux qui portent encore quelque chose de confirmé.
     *
     * Un arrêt né pendant la composition n'a que des services non confirmés :
     * le montrer vide dirait qu'il se passe quelque chose sans dire quoi.
     *
     * @return list<array<string, mixed>>
     */
    private function visibleStops(): array
    {
        return $this->stops
            ->map(fn ($stop) => new TourStopResource($stop, $this->resource, $this->includePending))
            ->filter(fn (TourStopResource $resource): bool => $this->locked_at === null
                || $this->includePending
                || $resource->toArray(request())['serviceCount'] > 0)
            ->map(fn (TourStopResource $resource) => $resource->toArray(request()))
            ->values()
            ->all();
    }

    /**
     * Commandes distinctes portées par la tournée.
     *
     * Deux services d'une même commande, même posés sur deux arrêts — le
     * chargement au dépôt et la livraison chez le client — ne font qu'une
     * commande. Les compter séparément doublerait le chiffre annoncé.
     */
    private function distinctOrderCount(): int
    {
        $confirmed = app(ConfirmedContent::class);

        return $this->stops
            ->flatMap(fn ($stop) => $confirmed->servicesOf($this->resource, $stop, $this->includePending))
            ->map(fn ($assignment) => $assignment->orderService?->order_id)
            ->filter()
            ->unique()
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'tourNumber' => $this->tour_number,
            'tourDate' => $this->tour_date?->toDateString(),
            'agencyId' => $this->agency_id,
            'depotId' => $this->depot_id,
            'providerId' => $this->provider_id,
            'vehicleId' => $this->vehicle_id,
            'driverId' => $this->driver_id,
            'tourType' => $this->tour_type,
            'plannedStartAt' => $this->planned_start_at?->toIso8601String(),
            'plannedEndAt' => $this->planned_end_at?->toIso8601String(),
            'totalWeight' => $this->total_weight,
            'totalVolume' => $this->total_volume,
            'totalPackages' => $this->total_packages,
            'totalCustomers' => $this->total_customers,
            'distanceMeters' => $this->distance_meters,
            'status' => $this->status->value,
            'agencyName' => $this->whenLoaded('agency', fn () => $this->agency->name),
            // Qui conduit, et avec quoi : une colonne qui ne montre qu'un
            // identifiant oblige a ouvrir la fiche pour savoir si la tournee
            // est affectee.
            'driverName' => $this->whenLoaded('driver', fn () => $this->driver?->name),
            'vehicleRegistration' => $this->whenLoaded('vehicle', fn () => $this->vehicle?->registration_number),
            // Nombre de commandes distinctes, calcule sur les arrets deja
            // charges : `tours` n'en porte pas le compte, et l'ajouter en base
            // donnerait un total de plus a tenir a jour a chaque mouvement.
            'orderCount' => $this->whenLoaded('stops', fn (): int => $this->distinctOrderCount()),
            // Qui réserve ce brouillon. Nommé plutôt que tu : un autre
            // planificateur qui trouve la tournée en lecture seule doit savoir
            // à qui demander de la libérer.
            'plannedBy' => $this->planners[$this->id] ?? null,
            // Qui la compose en ce moment. Distinct du créateur : la
            // réservation se prend au premier geste et se rend quand on a fini,
            // sans que le statut bouge.
            'lockedBy' => $this->holders[$this->id] ?? null,
            'lockedAt' => $this->locked_at?->toIso8601String(),
            'stopCount' => $this->whenCounted('stops'),
            // Les arrêts sont rendus **par la tournée** : c'est elle qui sait
            // si une composition est en cours, donc ce qui doit rester caché.
            'stops' => $this->whenLoaded('stops', fn () => $this->visibleStops()),
            // Dire ce qui attend plutôt que le taire : une tournée dont le
            // contenu semble figé alors qu'on la compose ailleurs inquiéterait.
            'pendingChanges' => $this->whenLoaded(
                'stops',
                fn (): int => app(ConfirmedContent::class)->pendingCount($this->resource),
            ),
            'periodCount' => $this->whenCounted('periods'),
        ];
    }
}
