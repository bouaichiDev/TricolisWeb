<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Queries;

use App\Http\Requests\Api\V1\ProviderSettlements\ListProviderSettlementRequest;
use App\Modules\Billing\Queries\InvoiceListQuery;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des décomptes fournisseurs.
 *
 * @see InvoiceListQuery pour le pendant client
 */
final readonly class ProviderSettlementListQuery
{
    /** @var list<string> */
    private const array SORTABLE = [
        'settlement_number', 'period_from', 'period_to',
        'subtotal', 'tax_total', 'total', 'status',
    ];

    /**
     * @param  array<string, string>  $scoped
     */
    public function paginate(ListProviderSettlementRequest $request, string $organizationId, array $scoped = []): LengthAwarePaginator
    {
        $query = ProviderSettlement::inOrganization($organizationId)
            ->with('provider:id,code,name')
            ->withCount('lines');

        foreach ($scoped as $column => $value) {
            $query->where($column, $value);
        }

        if ($request->filled('search')) {
            $query->where('settlement_number', 'like', '%'.$request->validated('search').'%');
        }

        foreach ([
            'providerId' => 'provider_id',
            'settlementNumber' => 'settlement_number',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input) && ! array_key_exists($column, $scoped)) {
                $query->where($column, $request->validated($input));
            }
        }

        if ($request->filled('periodFrom')) {
            $query->whereDate('period_from', '>=', $request->validated('periodFrom'));
        }

        if ($request->filled('periodTo')) {
            $query->whereDate('period_to', '<=', $request->validated('periodTo'));
        }

        return $query
            ->orderBy($request->getSort('settlement_number', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
