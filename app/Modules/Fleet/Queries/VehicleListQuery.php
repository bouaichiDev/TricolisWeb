<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Queries;

use App\Http\Requests\Api\V1\Fleet\ListVehicleRequest;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des véhicules.
 *
 * Isolation par le fournisseur, comme pour les chauffeurs. Les filtres de
 * capacité sont des minima : ils servent à trouver un véhicule capable de
 * porter une charge donnée.
 */
final readonly class VehicleListQuery
{
    /** @var list<string> */
    private const array SORTABLE = [
        'code', 'registration_number', 'payload_capacity', 'volume_capacity', 'pallet_capacity', 'status',
    ];

    public function paginate(ListVehicleRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = Vehicle::inOrganization($organizationId)
            ->with(['provider:id,code,name', 'vehicleType:id,code,name']);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$search}%")
                ->orWhere('registration_number', 'like', "%{$search}%"));
        }

        foreach ([
            'providerId' => 'provider_id',
            'vehicleTypeId' => 'vehicle_type_id',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        foreach ([
            'payloadCapacityMin' => 'payload_capacity',
            'volumeCapacityMin' => 'volume_capacity',
            'palletCapacityMin' => 'pallet_capacity',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, '>=', $request->validated($input));
            }
        }

        return $query
            ->orderBy($request->getSort('code', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
