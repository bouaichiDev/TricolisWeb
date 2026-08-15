<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\DTOs\CreateCommunicationTemplateData;
use App\Modules\Communications\DTOs\UpdateCommunicationTemplateData;
use App\Modules\Communications\Exceptions\CommunicationTemplateInUse;
use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Communications\Services\CommunicationScopeGuard;
use App\Modules\Communications\Services\ValidateCommunicationTemplateVariables;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'un modèle de message.
 *
 * La suppression est refusée dès qu'une règle ou une communication le
 * référence : les clés étrangères sont en RESTRICT, mais le refus métier arrive
 * avant, avec un message explicite.
 */
final readonly class ManageCommunicationTemplateAction
{
    /** @var list<string> */
    private const array AUDITED = [
        'service_id', 'code', 'name', 'channel', 'template_type', 'language', 'is_default', 'is_active',
    ];

    public function __construct(
        private CommunicationScopeGuard $guard,
        private ValidateCommunicationTemplateVariables $variables,
        private WriteCommunicationAudit $writer,
    ) {}

    public function create(CreateCommunicationTemplateData $data, AuditContext $context): CommunicationTemplate
    {
        $attributes = $data->attributes;

        $this->guard->service($attributes['service_id'] ?? null, $context->organizationId);
        $attributes['available_variables'] = $this->variables->validate($attributes['available_variables'] ?? null);

        return DB::transaction(function () use ($attributes, $context): CommunicationTemplate {
            $template = CommunicationTemplate::create($attributes)->refresh();

            $this->writer->created($template, 'communication_template.created', self::AUDITED, $context);

            return $template;
        });
    }

    public function update(
        CommunicationTemplate $template,
        UpdateCommunicationTemplateData $data,
        AuditContext $context,
    ): CommunicationTemplate {
        $attributes = $data->attributes->all();

        if (array_key_exists('service_id', $attributes)) {
            $this->guard->service($attributes['service_id'], $template->organization_id);
        }

        if (array_key_exists('available_variables', $attributes)) {
            $attributes['available_variables'] = $this->variables->validate($attributes['available_variables']);
        }

        /** @var CommunicationTemplate $updated */
        $updated = $this->writer->update($template, $attributes, 'communication_template.updated', $context);

        return $updated;
    }

    public function delete(CommunicationTemplate $template, AuditContext $context): void
    {
        if ($template->rules()->exists()) {
            throw CommunicationTemplateInUse::hasRules();
        }

        if ($template->communications()->exists()) {
            throw CommunicationTemplateInUse::hasCommunications();
        }

        DB::transaction(function () use ($template, $context): void {
            $this->writer->deleted($template, 'communication_template.deleted', self::AUDITED, $context);
            $template->delete();
        });
    }
}
