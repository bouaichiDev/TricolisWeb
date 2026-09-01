<?php

declare(strict_types=1);

namespace App\Modules\Templates\Queries;

use App\Http\Requests\Api\V1\Templates\ListTemplateRequest;
use App\Modules\Templates\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche paginée des modèles.
 *
 * `body_template` est cherchable mais **jamais triable** : trier un LONGTEXT
 * impose un tri de fichier sur des mégaoctets, et une mise en page de facture
 * en pèse plusieurs à elle seule.
 *
 * `customerId=global` ne filtre pas sur la valeur « global » : il demande les
 * modèles **sans** client. Sans cette sentinelle, l'écran ne saurait pas
 * distinguer « les modèles du transporteur » de « tous les modèles ».
 */
final readonly class TemplateListQuery
{
    /** @var list<string> */
    private const array SORTABLE = [
        'code', 'name', 'channel', 'template_type', 'language',
        'is_default', 'is_active', 'created_at', 'updated_at',
    ];

    /** @var list<string> */
    private const array SEARCHABLE = ['code', 'name', 'subject_template', 'body_template'];

    /** @var array<string, string> */
    private const array FILTERS = [
        'organizationId' => 'organization_id',
        'serviceId' => 'service_id',
        'channel' => 'channel',
        'templateType' => 'template_type',
        'language' => 'language',
        'isDefault' => 'is_default',
        'isActive' => 'is_active',
    ];

    public function paginate(ListTemplateRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = Template::inOrganization($organizationId)
            ->with(['customer:id,code,name', 'service:id,code,name']);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(function (Builder $builder) use ($search): void {
                foreach (self::SEARCHABLE as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        foreach (self::FILTERS as $input => $column) {
            if ($request->has($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        $this->filterCustomer($query, $request);

        $sort = $request->getSort('code', self::SORTABLE);
        $direction = $request->validated('direction') ?? 'asc';

        return $query->orderBy($sort, $direction)->paginate($request->getPerPage());
    }

    /**
     * @param  Builder<Template>  $query
     */
    private function filterCustomer(Builder $query, ListTemplateRequest $request): void
    {
        if (! $request->has('customerId')) {
            return;
        }

        $customerId = $request->validated('customerId');

        if ($customerId === ListTemplateRequest::GLOBAL_SCOPE) {
            $query->whereNull('customer_id');

            return;
        }

        $query->where('customer_id', $customerId);
    }
}
