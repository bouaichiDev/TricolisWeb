<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie le périmètre des références d'intégration.
 *
 * Aucune des quatre classes de la phase ne porte `organizationId` : le périmètre
 * passe par le client, en une jointure.
 */
final readonly class IntegrationScopeGuard
{
    public function customer(string $customerId, string $organizationId): Customer
    {
        $customer = Customer::where('organization_id', $organizationId)->whereKey($customerId)->first();

        return $customer ?? $this->fail('customerId', 'Ce client n’appartient pas à l’organisation active.');
    }

    /**
     * La configuration doit être accessible et **active**.
     *
     * Le §28 l'exige : déclencher un export sur une configuration désactivée
     * produirait un job que personne ne traitera jamais.
     */
    public function activeExportConfiguration(string $configurationId, string $organizationId): CustomerExportConfiguration
    {
        $configuration = CustomerExportConfiguration::inOrganization($organizationId)
            ->whereKey($configurationId)
            ->first();

        if ($configuration === null) {
            $this->fail('configurationId', 'Cette configuration d’export n’est pas accessible dans l’organisation active.');
        }

        if (! $configuration->is_active) {
            $this->fail('configurationId', 'Cette configuration d’export est désactivée.');
        }

        return $configuration;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
