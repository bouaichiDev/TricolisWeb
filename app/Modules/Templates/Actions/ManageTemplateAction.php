<?php

declare(strict_types=1);

namespace App\Modules\Templates\Actions;

use App\Modules\Templates\DTOs\CreateTemplateData;
use App\Modules\Templates\DTOs\UpdateTemplateData;
use App\Modules\Templates\Exceptions\TemplateInUse;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\TemplateScopeGuard;
use App\Modules\Templates\Services\ValidateTemplateVariables;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'un modèle.
 *
 * La suppression est refusée dès qu'une règle, une communication ou une facture
 * le référence : les clés étrangères sont en RESTRICT, mais le refus métier
 * arrive avant, avec un message explicite.
 */
final readonly class ManageTemplateAction
{
    /** @var list<string> */
    private const array AUDITED = [
        'customer_id', 'service_id', 'code', 'name', 'channel',
        'template_type', 'language', 'is_default', 'is_active',
    ];

    public function __construct(
        private TemplateScopeGuard $guard,
        private ValidateTemplateVariables $variables,
        private WriteTemplateAudit $writer,
    ) {}

    public function create(CreateTemplateData $data, AuditContext $context): Template
    {
        $attributes = $data->attributes;

        $this->guard->service($attributes['service_id'] ?? null, $context->organizationId);
        $this->guard->customer($attributes['customer_id'] ?? null, $context->organizationId);
        $attributes['available_variables'] = $this->variables->validate($attributes['available_variables'] ?? null);

        return DB::transaction(function () use ($attributes, $context): Template {
            $template = Template::create($attributes)->refresh();

            $this->writer->created($template, 'template.created', self::AUDITED, $context);

            return $template;
        });
    }

    public function update(Template $template, UpdateTemplateData $data, AuditContext $context): Template
    {
        $attributes = $data->attributes->all();

        if (array_key_exists('service_id', $attributes)) {
            $this->guard->service($attributes['service_id'], $template->organization_id);
        }

        if (array_key_exists('customer_id', $attributes)) {
            $this->guard->customer($attributes['customer_id'], $template->organization_id);
        }

        if (array_key_exists('available_variables', $attributes)) {
            $attributes['available_variables'] = $this->variables->validate($attributes['available_variables']);
        }

        /** @var Template $updated */
        $updated = $this->writer->update($template, $attributes, 'template.updated', $context);

        return $updated;
    }

    public function delete(Template $template, AuditContext $context): void
    {
        if ($template->rules()->exists()) {
            throw TemplateInUse::hasRules();
        }

        if ($template->communications()->exists()) {
            throw TemplateInUse::hasCommunications();
        }

        if ($template->invoices()->exists()) {
            throw TemplateInUse::hasInvoices();
        }

        DB::transaction(function () use ($template, $context): void {
            $this->writer->deleted($template, 'template.deleted', self::AUDITED, $context);
            $template->delete();
        });
    }
}
