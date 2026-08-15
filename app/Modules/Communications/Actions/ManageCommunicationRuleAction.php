<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\DTOs\CreateCommunicationRuleData;
use App\Modules\Communications\DTOs\UpdateCommunicationRuleData;
use App\Modules\Communications\Exceptions\CommunicationTemplateInUse;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Services\CommunicationRuleConditionEvaluator;
use App\Modules\Communications\Services\CommunicationScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'une règle de communication.
 *
 * Deux vérifications de périmètre s'ajoutent à celle de l'organisation : le
 * template doit y appartenir, et si ce template est restreint à un service, la
 * règle ne peut pas en viser un autre — elle produirait un message conçu pour
 * une prestation différente.
 */
final readonly class ManageCommunicationRuleAction
{
    /** @var list<string> */
    private const array AUDITED = [
        'service_id', 'template_id', 'event_type', 'recipient_role',
        'delay_value', 'delay_unit', 'is_automatic', 'is_active',
    ];

    public function __construct(
        private CommunicationScopeGuard $guard,
        private CommunicationRuleConditionEvaluator $evaluator,
        private WriteCommunicationAudit $writer,
    ) {}

    public function create(CreateCommunicationRuleData $data, AuditContext $context): CommunicationRule
    {
        $attributes = $data->attributes;

        $template = $this->guard->template($attributes['template_id'], $context->organizationId);
        $this->guard->service($attributes['service_id'] ?? null, $context->organizationId);
        $this->guard->ruleMatchesTemplateService($template, $attributes['service_id'] ?? null);

        $attributes['conditions'] = $this->evaluator->validate($attributes['conditions'] ?? null);

        return DB::transaction(function () use ($attributes, $context): CommunicationRule {
            $rule = CommunicationRule::create($attributes)->refresh();

            $this->writer->created($rule, 'communication_rule.created', self::AUDITED, $context);

            return $rule;
        });
    }

    public function update(
        CommunicationRule $rule,
        UpdateCommunicationRuleData $data,
        AuditContext $context,
    ): CommunicationRule {
        $attributes = $data->attributes->all();
        $organizationId = $rule->organization_id;

        $template = array_key_exists('template_id', $attributes)
            ? $this->guard->template($attributes['template_id'], $organizationId)
            : $this->guard->template($rule->template_id, $organizationId);

        $serviceId = array_key_exists('service_id', $attributes) ? $attributes['service_id'] : $rule->service_id;

        if (array_key_exists('service_id', $attributes)) {
            $this->guard->service($attributes['service_id'], $organizationId);
        }

        $this->guard->ruleMatchesTemplateService($template, $serviceId);

        if (array_key_exists('conditions', $attributes)) {
            $attributes['conditions'] = $this->evaluator->validate($attributes['conditions']);
        }

        /** @var CommunicationRule $updated */
        $updated = $this->writer->update($rule, $attributes, 'communication_rule.updated', $context);

        return $updated;
    }

    public function delete(CommunicationRule $rule, AuditContext $context): void
    {
        if ($rule->communications()->exists()) {
            throw CommunicationTemplateInUse::ruleHasCommunications();
        }

        DB::transaction(function () use ($rule, $context): void {
            $this->writer->deleted($rule, 'communication_rule.deleted', self::AUDITED, $context);
            $rule->delete();
        });
    }
}
