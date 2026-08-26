<?php

declare(strict_types=1);

namespace App\Modules\Tours\Queries;

use App\Http\Requests\Api\V1\Tours\ListTourRequest;
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
            ->with(['agency:id,code,name'])
            ->withCount(['stops', 'periods']);

        // Chargement anticipe, pas une requete par colonne : la vue en
        // colonnes affiche cinq tournees et leurs arrets d'un coup.
        if ($request->boolean('withStops')) {
            $query->with(['stops' => fn ($stops) => $stops->orderBy('sequence')->with('address')->withCount('services')]);
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
