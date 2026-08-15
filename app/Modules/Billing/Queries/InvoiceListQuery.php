<?php

declare(strict_types=1);

namespace App\Modules\Billing\Queries;

use App\Http\Requests\Api\V1\Billing\ListInvoiceRequest;
use App\Modules\Billing\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des factures de l'organisation active.
 *
 * Les lignes ne sont jamais chargées — le §28 l'interdit — seulement leur
 * compteur.
 */
final readonly class InvoiceListQuery
{
    /** @var list<string> */
    private const array SORTABLE = [
        'invoice_number', 'invoice_date', 'period_from', 'period_to',
        'subtotal', 'tax_total', 'total', 'status', 'created_at',
    ];

    public function paginate(ListInvoiceRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = Invoice::inOrganization($organizationId)
            ->with('customer:id,code,name')
            ->withCount('lines');

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(function ($builder) use ($search): void {
                foreach (['invoice_number', 'external_reference', 'remark'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        foreach ([
            'customerId' => 'customer_id',
            'invoiceNumber' => 'invoice_number',
            'currencyCode' => 'currency_code',
            'status' => 'status',
            'externalReference' => 'external_reference',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        foreach ([
            'invoiceDateFrom' => ['invoice_date', '>='],
            'invoiceDateTo' => ['invoice_date', '<='],
            'periodFrom' => ['period_from', '>='],
            'periodTo' => ['period_to', '<='],
        ] as $input => [$column, $operator]) {
            if ($request->filled($input)) {
                $query->whereDate($column, $operator, $request->validated($input));
            }
        }

        $sort = $request->getSort('invoice_date', self::SORTABLE);
        $direction = $request->validated('direction') ?? ($sort === 'invoice_date' ? 'desc' : 'asc');

        return $query->orderBy($sort, $direction)->paginate($request->getPerPage());
    }
}
