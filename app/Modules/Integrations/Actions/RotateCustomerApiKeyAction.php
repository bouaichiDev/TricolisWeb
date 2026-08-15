<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Services\ApiKeyGenerator;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Remplace la clé d'un accès API.
 *
 * L'ancienne est invalidée **à l'instant même** : l'empreinte est écrasée, et
 * l'unicité de `api_key_hash` garantit qu'aucune trace ne subsiste. Aucune table
 * d'historique n'est créée — le §13 l'interdit.
 *
 * L'audit note la rotation, jamais la clé.
 */
final readonly class RotateCustomerApiKeyAction
{
    public function __construct(
        private ApiKeyGenerator $keys,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @return array{configuration: CustomerApiConfiguration, key: string}
     */
    public function execute(CustomerApiConfiguration $configuration, AuditContext $context): array
    {
        $generated = $this->keys->generate();

        $rotated = DB::transaction(function () use ($configuration, $generated, $context): CustomerApiConfiguration {
            $configuration->update(['api_key_hash' => $generated['hash']]);

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'customer_api_configuration.key_rotated',
                $configuration,
                null,
                $configuration->only(['customer_id', 'name']),
                null,
                $context->ipAddress,
            );

            return $configuration->fresh();
        });

        return ['configuration' => $rotated, 'key' => $generated['key']];
    }
}
