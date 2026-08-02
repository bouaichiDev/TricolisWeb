<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Modules\Integrations\DTOs\UpdateApiConfigurationData;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modification et suppression d'un accès API.
 *
 * La création vit dans `CreateCustomerApiConfigurationAction` : elle génère une
 * clé et retourne un secret, ce que ces deux méthodes ne font jamais.
 */
final readonly class ManageApiConfigurationAction
{
    /** @var list<string> */
    private const array AUDITED = ['customer_id', 'name', 'is_active'];

    public function __construct(private WriteConfigurationAudit $writer) {}

    public function update(
        CustomerApiConfiguration $configuration,
        UpdateApiConfigurationData $data,
        AuditContext $context,
    ): CustomerApiConfiguration {
        return $this->writer->update(
            $configuration,
            $data->attributes->all(),
            'customer_api_configuration.updated',
            self::AUDITED,
            $context,
        );
    }

    public function delete(CustomerApiConfiguration $configuration, AuditContext $context): void
    {
        DB::transaction(function () use ($configuration, $context): void {
            $this->writer->deleted($configuration, 'customer_api_configuration.deleted', self::AUDITED, $context);
            $configuration->delete();
        });
    }
}
