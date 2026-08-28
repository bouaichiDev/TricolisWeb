<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

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
     * @param  array<string, array{id: string, name: string}>  $planners  par tournée
     */
    public function __construct($resource, private readonly array $planners = [])
    {
        parent::__construct($resource);
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
        return $this->stops
            ->flatMap(fn ($stop) => $stop->relationLoaded('services') ? $stop->services : [])
            ->filter(fn ($assignment): bool => (bool) $assignment->is_active_assignment)
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
            'stopCount' => $this->whenCounted('stops'),
            'stops' => TourStopResource::collection($this->whenLoaded('stops')),
            'periodCount' => $this->whenCounted('periods'),
        ];
    }
}
