<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Modules\Integrations\DTOs\CreateImportConfigurationData;
use App\Modules\Integrations\DTOs\UpdateImportConfigurationData;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\IntegrationScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'une configuration d'import.
 *
 * Aucun moteur d'import n'est créé : le §8 le dit, le diagramme définit une
 * configuration, pas une exécution.
 */
final readonly class ManageImportConfigurationAction
{
    /** @var list<string> */
    private const array AUDITED = ['customer_id', 'name', 'source_type', 'file_format', 'is_active'];

    public function __construct(
        private IntegrationScopeGuard $guard,
        private WriteConfigurationAudit $writer,
    ) {}

    public function create(CreateImportConfigurationData $data, AuditContext $context): CustomerImportConfiguration
    {
        $this->guard->customer($data->customerId, $context->organizationId);

        return DB::transaction(function () use ($data, $context): CustomerImportConfiguration {
            $configuration = CustomerImportConfiguration::create($data->toAttributes())->refresh();

            $this->writer->created($configuration, 'customer_import_configuration.created', self::AUDITED, $context);

            return $configuration;
        });
    }

    public function update(
        CustomerImportConfiguration $configuration,
        UpdateImportConfigurationData $data,
        AuditContext $context,
    ): CustomerImportConfiguration {
        return $this->writer->update(
            $configuration,
            $data->attributes->all(),
            'customer_import_configuration.updated',
            $context,
        );
    }

    public function delete(CustomerImportConfiguration $configuration, AuditContext $context): void
    {
        DB::transaction(function () use ($configuration, $context): void {
            $this->writer->deleted($configuration, 'customer_import_configuration.deleted', self::AUDITED, $context);
            $configuration->delete();
        });
    }
}
