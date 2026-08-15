<?php

declare(strict_types=1);

namespace App\Modules\Tours\Queries;

use App\Http\Requests\Api\V1\Tours\ListTourPeriodRequest;
use App\Modules\Tours\Models\Tour;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des périodes d'une tournée.
 *
 * Le périmètre organisationnel est déjà tenu par la tournée : la route est
 * imbriquée sous `/tours/{tour}`, et le contrôleur a vérifié son appartenance
 * avant d'arriver ici.
 */
final readonly class TourPeriodListQuery
{
    /** @var list<string> */
    private const array SORTABLE = [
        'sequence', 'period_type', 'planned_start_at', 'planned_end_at',
        'actual_start_at', 'actual_end_at', 'distance_meters', 'status',
    ];

    public function paginate(ListTourPeriodRequest $request, Tour $tour): LengthAwarePaginator
    {
        $query = $tour->periods()->withCount('assignments');

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('period_type', 'like', "%{$search}%")
                ->orWhere('internal_remark', 'like', "%{$search}%"));
        }

        foreach ([
            'tourStopId' => 'tour_stop_id',
            'periodType' => 'period_type',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        foreach ([
            'plannedFrom' => ['planned_start_at', '>='],
            'plannedTo' => ['planned_start_at', '<='],
            'actualFrom' => ['actual_start_at', '>='],
            'actualTo' => ['actual_start_at', '<='],
        ] as $input => [$column, $operator]) {
            if ($request->filled($input)) {
                $query->where($column, $operator, $request->validated($input));
            }
        }

        return $query
            ->orderBy($request->getSort('sequence', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
