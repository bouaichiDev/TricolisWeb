<?php

declare(strict_types=1);

namespace App\Modules\Exports\Actions;

use App\Modules\Exports\DTOs\CreateExportConfigurationData;
use App\Modules\Exports\DTOs\UpdateExportConfigurationData;
use App\Modules\Exports\Exceptions\ExportConfigurationInUse;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Integrations\Actions\WriteConfigurationAudit;
use App\Modules\Integrations\Services\IntegrationScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'une configuration d'export.
 *
 * La suppression est refusée dès qu'un `ExportJob` la référence : un job
 * documente ce qui a été envoyé, et la configuration explique comment. La clé
 * étrangère est en `RESTRICT` ; le refus métier arrive avant.
 */
final readonly class ManageExportConfigurationAction
{
    /** @var list<string> */
    private const array AUDITED = ['customer_id', 'name', 'export_type', 'format', 'transport', 'is_active'];

    public function __construct(
        private IntegrationScopeGuard $guard,
        private WriteConfigurationAudit $writer,
    ) {}

    public function create(CreateExportConfigurationData $data, AuditContext $context): CustomerExportConfiguration
    {
        $this->guard->customer($data->customerId, $context->organizationId);

        return DB::transaction(function () use ($data, $context): CustomerExportConfiguration {
            $configuration = CustomerExportConfiguration::create($data->toAttributes())->refresh();

            $this->writer->created($configuration, 'customer_export_configuration.created', self::AUDITED, $context);

            return $configuration;
        });
    }

    public function update(
        CustomerExportConfiguration $configuration,
        UpdateExportConfigurationData $data,
        AuditContext $context,
    ): CustomerExportConfiguration {
        return $this->writer->update(
            $configuration,
            $data->attributes->all(),
            'customer_export_configuration.updated',
            $context,
        );
    }

    public function delete(CustomerExportConfiguration $configuration, AuditContext $context): void
    {
        if ($configuration->jobs()->exists()) {
            throw ExportConfigurationInUse::hasJobs();
        }

        DB::transaction(function () use ($configuration, $context): void {
            $this->writer->deleted($configuration, 'customer_export_configuration.deleted', self::AUDITED, $context);
            $configuration->delete();
        });
    }
}
