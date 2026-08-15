<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Integrations\DTOs\CreateApiConfigurationData;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Services\ApiKeyGenerator;
use App\Modules\Integrations\Services\IntegrationScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un accès API et sa clé.
 *
 * La clé est **générée ici**, jamais fournie par l'appelant. Elle est retournée
 * une seule fois, dans un objet à part de la configuration, pour qu'aucune
 * lecture ultérieure ne puisse la restituer par accident.
 *
 * L'audit enregistre la création de l'accès — **pas la clé**, ni même son
 * empreinte : un journal d'audit se consulte plus largement qu'une table.
 */
final readonly class CreateCustomerApiConfigurationAction
{
    public function __construct(
        private IntegrationScopeGuard $guard,
        private ApiKeyGenerator $keys,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @return array{configuration: CustomerApiConfiguration, key: string}
     */
    public function execute(CreateApiConfigurationData $data, AuditContext $context): array
    {
        $this->guard->customer($data->customerId, $context->organizationId);

        $generated = $this->keys->generate();

        $configuration = DB::transaction(function () use ($data, $generated, $context): CustomerApiConfiguration {
            $configuration = CustomerApiConfiguration::create($data->toAttributes($generated['hash']))->refresh();

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'customer_api_configuration.created',
                $configuration,
                null,
                $configuration->only(['customer_id', 'name', 'is_active']),
                null,
                $context->ipAddress,
            );

            return $configuration;
        });

        return ['configuration' => $configuration, 'key' => $generated['key']];
    }
}
