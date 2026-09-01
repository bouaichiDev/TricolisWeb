<?php

declare(strict_types=1);

namespace App\Modules\Communications\Queries;

use App\Http\Requests\Api\V1\Communications\ListCommunicationRequest;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Templates\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche paginée des trois ressources de communication.
 *
 * Les trois partagent la même mécanique — périmètre par l'organisation,
 * recherche textuelle, filtres exacts, intervalles de dates. Trois Query Objects
 * n'auraient différé que par trois tableaux : ils sont donc portés ici, en
 * données, comme à la Phase 8.
 *
 * `body` et `body_template` sont cherchables mais **jamais triables** : trier un
 * LONGTEXT impose un tri de fichier sur des mégaoctets.
 */
final readonly class CommunicationListQuery
{
    /** @var array<string, array{sortable: list<string>, searchable: list<string>, filters: array<string, string>, default: string, direction: string}> */
    private const array PROFILES = [
        'template' => [
            'sortable' => ['code', 'name', 'channel', 'template_type', 'language', 'is_default', 'is_active', 'created_at', 'updated_at'],
            'searchable' => ['code', 'name', 'subject_template', 'body_template'],
            'filters' => [
                'organizationId' => 'organization_id', 'serviceId' => 'service_id', 'channel' => 'channel',
                'templateType' => 'template_type', 'language' => 'language',
                'isDefault' => 'is_default', 'isActive' => 'is_active',
            ],
            'default' => 'code',
            'direction' => 'asc',
        ],
        'rule' => [
            'sortable' => ['event_type', 'recipient_role', 'delay_value', 'delay_unit', 'is_automatic', 'is_active', 'created_at', 'updated_at'],
            'searchable' => ['delay_unit'],
            'filters' => [
                'organizationId' => 'organization_id', 'serviceId' => 'service_id', 'templateId' => 'template_id',
                'eventType' => 'event_type', 'recipientRole' => 'recipient_role', 'delayUnit' => 'delay_unit',
                'isAutomatic' => 'is_automatic', 'isActive' => 'is_active',
            ],
            'default' => 'event_type',
            'direction' => 'asc',
        ],
        'communication' => [
            'sortable' => ['scheduled_at', 'queued_at', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'status', 'channel', 'created_at', 'updated_at'],
            'searchable' => ['recipient_name', 'recipient_email', 'recipient_phone', 'subject', 'body', 'provider_message_id', 'error_message'],
            'filters' => [
                'organizationId' => 'organization_id', 'orderId' => 'order_id', 'templateId' => 'template_id',
                'communicationRuleId' => 'communication_rule_id', 'channel' => 'channel',
                'communicationType' => 'communication_type', 'recipientRole' => 'recipient_role',
                'status' => 'status', 'createdBy' => 'created_by',
            ],
            'default' => 'created_at',
            'direction' => 'desc',
        ],
    ];

    /** @var array<string, array<string, array{string, string}>> */
    private const array RANGES = [
        'communication' => [
            'scheduledFrom' => ['scheduled_at', '>='], 'scheduledTo' => ['scheduled_at', '<='],
            'sentFrom' => ['sent_at', '>='], 'sentTo' => ['sent_at', '<='],
            'failedFrom' => ['failed_at', '>='], 'failedTo' => ['failed_at', '<='],
        ],
    ];

    /**
     * @param  array<string, string>  $scoped
     */
    public function paginate(
        string $profile,
        ListCommunicationRequest $request,
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
        $direction = $request->validated('direction') ?? $config['direction'];

        return $query->orderBy($sort, $direction)->paginate($request->getPerPage());
    }

    /**
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    private function baseQuery(string $profile, string $organizationId): Builder
    {
        return match ($profile) {
            'template' => Template::inOrganization($organizationId),
            'rule' => CommunicationRule::inOrganization($organizationId)->with('template:id,code,name,channel'),
            'communication' => OrderCommunication::inOrganization($organizationId)
                ->with(['template:id,code,name', 'order:id,order_number'])
                ->withCount('attachments'),
        };
    }
}
