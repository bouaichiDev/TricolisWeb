<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Queries;

use App\Http\Requests\Api\V1\Integrations\ListConfigurationRequest;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche paginée des quatre ressources d'intégration.
 *
 * Les quatre partagent la même mécanique : périmètre par le client, recherche
 * textuelle, filtres exacts, intervalles de dates. Quatre Query Objects
 * n'auraient différé que par trois tableaux — ils sont donc portés ici, en
 * données.
 */
final readonly class IntegrationListQuery
{
    /** @var array<string, array{sortable: list<string>, searchable: list<string>, filters: array<string, string>, default: string}> */
    private const array PROFILES = [
        'import' => [
            'sortable' => ['name', 'source_type', 'file_format', 'is_active'],
            'searchable' => ['name', 'source_type', 'file_format'],
            'filters' => ['customerId' => 'customer_id', 'sourceType' => 'source_type', 'fileFormat' => 'file_format', 'isActive' => 'is_active'],
            'default' => 'name',
        ],
        'api' => [
            'sortable' => ['name', 'is_active', 'last_used_at'],
            'searchable' => ['name'],
            'filters' => ['customerId' => 'customer_id', 'isActive' => 'is_active'],
            'default' => 'name',
        ],
        'export' => [
            'sortable' => ['name', 'export_type', 'format', 'transport', 'frequency', 'is_active'],
            'searchable' => ['name', 'export_type', 'host', 'username'],
            'filters' => ['customerId' => 'customer_id', 'exportType' => 'export_type', 'format' => 'format', 'transport' => 'transport', 'frequency' => 'frequency', 'isActive' => 'is_active'],
            'default' => 'name',
        ],
        'job' => [
            'sortable' => ['generated_at', 'sent_at', 'attempt_count', 'status', 'file_name'],
            'searchable' => ['file_name', 'status', 'error_message', 'entity_type'],
            'filters' => ['customerId' => 'customer_id', 'configurationId' => 'configuration_id', 'entityType' => 'entity_type', 'entityId' => 'entity_id', 'status' => 'status'],
            'default' => 'generated_at',
        ],
    ];

    /** @var array<string, array<string, array{string, string}>> */
    private const array RANGES = [
        'api' => ['lastUsedFrom' => ['last_used_at', '>='], 'lastUsedTo' => ['last_used_at', '<=']],
        'job' => [
            'generatedFrom' => ['generated_at', '>='], 'generatedTo' => ['generated_at', '<='],
            'sentFrom' => ['sent_at', '>='], 'sentTo' => ['sent_at', '<='],
        ],
    ];

    /**
     * @param  array<string, string>  $scoped
     */
    public function paginate(
        string $profile,
        ListConfigurationRequest $request,
        string $organizationId,
        array $scoped = [],
    ): LengthAwarePaginator {
        $config = self::PROFILES[$profile];
        $query = $this->baseQuery($profile, $organizationId);

        foreach ($scoped as $column => $value) {
            $query->where($column, $value);
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(function (Builder $builder) use ($config, $search): void {
                foreach ($config['searchable'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        foreach ($config['filters'] as $input => $column) {
            if ($request->has($input) && ! array_key_exists($column, $scoped)) {
                $query->where($column, $request->validated($input));
            }
        }

        foreach (self::RANGES[$profile] ?? [] as $input => [$column, $operator]) {
            if ($request->filled($input)) {
                $query->where($column, $operator, $request->validated($input));
            }
        }

        $sort = $request->getSort($config['default'], $config['sortable']);
        $direction = $request->validated('direction') ?? ($sort === 'generated_at' ? 'desc' : 'asc');

        return $query->orderBy($sort, $direction)->paginate($request->getPerPage());
    }

    /**
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    private function baseQuery(string $profile, string $organizationId): Builder
    {
        return match ($profile) {
            'import' => CustomerImportConfiguration::inOrganization($organizationId),
            'api' => CustomerApiConfiguration::inOrganization($organizationId),
            'export' => CustomerExportConfiguration::inOrganization($organizationId),
            'job' => ExportJob::inOrganization($organizationId)->with('configuration:id,name,format,transport'),
        };
    }
}
