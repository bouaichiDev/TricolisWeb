<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Queries;

use App\Modules\Fleet\Models\VehicleType;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des types de véhicule de l'organisation active.
 */
final readonly class VehicleTypeListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['code', 'name', 'status'];

    public function paginate(ListRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = VehicleType::where('organization_id', $organizationId)->withCount('vehicles');

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        return $query
            ->orderBy($request->getSort('code', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
