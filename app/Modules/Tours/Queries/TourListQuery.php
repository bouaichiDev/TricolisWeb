<?php

declare(strict_types=1);

namespace App\Modules\Tours\Queries;

use App\Http\Requests\Api\V1\Tours\ListTourRequest;
use App\Modules\Planning\Actions\RecalculateTourRoute;
use App\Modules\Tours\Models\Tour;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des tournées de l'organisation active.
 *
 * Le filtre organisationnel est appliqué en premier et sans condition : aucune
 * combinaison de paramètres ne peut le contourner.
 *
 * Ni les arrêts, ni les périodes, ni les affectations ne sont chargés — le §26
 * l'interdit, et une liste de tournées n'a pas à parcourir tout l'agrégat.
 */
final readonly class TourListQuery
{
    /** @var list<string> */
    private const array SORTABLE = [
        'tour_number', 'tour_date', 'planned_start_at', 'planned_end_at',
        'actual_start_at', 'actual_end_at', 'total_weight', 'total_volume',
        'total_packages', 'total_customers', 'driving_time_minutes',
        'working_time_minutes', 'distance_meters', 'status',
    ];

    public function paginate(ListTourRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = Tour::inOrganization($organizationId)
            ->with([
                'agency:id,code,name',
                // Charges d'emblee : la colonne les montre, et les demander
                // tournee par tournee ferait une requete par colonne.
                'driver:id,code,name',
                'vehicle:id,code,registration_number',
            ])
            ->withCount(['stops', 'periods']);

        // Chargement anticipe, pas une requete par colonne : la vue en
        // colonnes affiche cinq tournees et leurs arrets d'un coup.
        if ($request->boolean('withStops')) {
            // Seules les affectations actives sont comptees : une tournee
            // confirmee garde la trace des services qu'on lui a retires, et
            // les compter ferait annoncer au camion un arret qu'il ne fait
            // plus.
            $query->with(['stops' => fn ($stops) => $stops
                ->orderBy('sequence')
                ->with(['address', 'services' => fn ($services) => $services
                    ->where('is_active_assignment', true)
                    ->with([
                        'orderService:id,order_id,service_id,service_number,weight,volume,package_count,required_time_minutes,status',
                        'orderService.order:id,order_number,customer_id,customer_reference',
                        'orderService.order.customer:id,code,name',
                        'orderService.service:id,code,name',
                    ])])
                ->withCount(['services' => fn ($services) => $services->where('is_active_assignment', true)]),
            ]);

            // Les trajets suivent les arrets qu'ils relient : les demander
            // ensuite, colonne par colonne, ferait une requete de plus par
            // tournee pour une poignee de lignes.
            $query->with(['periods' => fn ($periods) => $periods
                ->where('period_type', RecalculateTourRoute::PERIOD_TYPE)
                ->orderBy('sequence')]);
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('tour_number', 'like', "%{$search}%")
                ->orWhere('instructions', 'like', "%{$search}%"));
        }

        foreach ([
            'agencyId' => 'agency_id',
            'depotId' => 'depot_id',
            'providerId' => 'provider_id',
            'driverId' => 'driver_id',
            'vehicleId' => 'vehicle_id',
            'tourDate' => 'tour_date',
            'tourType' => 'tour_type',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        if ($request->filled('tourDateFrom')) {
            $query->whereDate('tour_date', '>=', $request->validated('tourDateFrom'));
        }

        if ($request->filled('tourDateTo')) {
            $query->whereDate('tour_date', '<=', $request->validated('tourDateTo'));
        }

        return $query
            ->orderBy($request->getSort('tour_date', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
