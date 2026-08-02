<?php

declare(strict_types=1);

namespace App\Modules\Customers\Queries;

use App\Modules\Customers\Models\Customer;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des clients d'une organisation.
 *
 * Les colonnes de tri et les champs de recherche sont en liste blanche.
 */
final readonly class CustomerListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['name', 'code', 'created_at'];

    /** @var list<string> */
    private const array SEARCHABLE = ['name', 'code', 'legal_name', 'email'];

    public function paginate(ListRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = Customer::where('organization_id', $organizationId);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(function ($builder) use ($search): void {
                foreach (self::SEARCHABLE as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        return $query
            ->orderBy($request->getSort('name', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
