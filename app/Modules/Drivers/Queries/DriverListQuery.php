<?php

declare(strict_types=1);

namespace App\Modules\Drivers\Queries;

use App\Http\Requests\Api\V1\Drivers\ListDriverRequest;
use App\Modules\Drivers\Models\Driver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des chauffeurs.
 *
 * Le chauffeur n'ayant pas d'organisation propre, l'isolation passe par le
 * scope `inOrganization`, qui joint le fournisseur.
 */
final readonly class DriverListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['code', 'first_name', 'last_name', 'status', 'legacy_id'];

    public function paginate(ListDriverRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = Driver::inOrganization($organizationId)->with('provider:id,code,name');

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        foreach (['providerId' => 'provider_id', 'userId' => 'user_id', 'status' => 'status', 'legacyId' => 'legacy_id'] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        return $query
            ->orderBy($request->getSort('code', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
