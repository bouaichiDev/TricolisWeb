<?php

declare(strict_types=1);

namespace App\Modules\Drivers\Queries;

use App\Http\Requests\Api\V1\Drivers\ListDriverRequest;
use App\Modules\Drivers\Models\Driver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des chauffeurs de l'organisation active.
 *
 * Le chauffeur porte son `organization_id` : l'isolation est une simple
 * condition, sans jointure sur le fournisseur.
 */
final readonly class DriverListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['code', 'name', 'status'];

    public function paginate(ListDriverRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = Driver::inOrganization($organizationId)->with('provider:id,code,name');

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        foreach ([
            'providerId' => 'provider_id',
            'addressId' => 'address_id',
            'contactId' => 'contact_id',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        return $query
            ->orderBy($request->getSort('code', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
