<?php

declare(strict_types=1);

namespace App\Modules\Providers\Queries;

use App\Http\Requests\Api\V1\Providers\ListProviderRequest;
use App\Modules\Providers\Models\Provider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des fournisseurs de l'organisation active.
 *
 * Le filtre organisationnel est appliqué en premier et sans condition : aucune
 * combinaison de paramètres ne peut le contourner.
 */
final readonly class ProviderListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['code', 'name', 'provider_type', 'status', 'legacy_id'];

    public function paginate(ListProviderRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = Provider::where('organization_id', $organizationId)
            ->withCount(['drivers', 'vehicles']);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        foreach (['status' => 'status', 'providerType' => 'provider_type', 'legacyId' => 'legacy_id'] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        return $query
            ->orderBy($request->getSort('code', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
