<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Enums\ExportTransport;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Services\Content\OrderStatusExportContent;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Sources\RestPullConnections;
use App\Modules\Orders\Models\Service;
use Database\Seeders\Support\CustomerIntegrationPresets;
use Illuminate\Database\Seeder;

/**
 * Intégrations de démonstration du portail (Phases 6 et 8), en local et en
 * test de parcours seulement — jamais en production.
 *
 * Les quatre cas de référence, en configurations **génériques** :
 *
 * - QOQA : dépôt Excel (prévisualisable dans le portail) ;
 * - IKEA : relève XML en SFTP (connexion déclarée au déploiement) ;
 * - SCHMIDT : pull REST JSON horaire, connexion **inactive** ;
 * - GETAZ : envoi REST des statuts de commande, destination **inactive**.
 *
 * Les adresses externes sont en `.invalid` (RFC 2606) et désactivées : la
 * démonstration n'appelle jamais un système réel. Aucun secret n'est posé.
 */
class DemoCustomerPortalIntegrationsSeeder extends Seeder
{
    private const array RULES = ['externalReference' => ['required', 'max:64'], '@onDuplicate' => 'skip'];

    public function run(): void
    {
        // Démonstration seulement : jamais en production, même avec `--force`.
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $qoqa = Customer::where('code', 'qoqa')->first();
        $ikea = Customer::where('code', 'ikea')->first();

        if ($qoqa === null || $ikea === null) {
            return;
        }

        $serviceCode = Service::where('organization_id', $qoqa->organization_id)->where('code', 'DELIVERY')->value('code');

        if ($serviceCode === null) {
            return;
        }

        $this->import($qoqa, 'Commandes Excel', 'upload', 'xlsx', CustomerIntegrationPresets::flatSpreadsheetOrders($serviceCode));
        $this->import($ikea, 'Commandes XML (SFTP)', 'sftp', 'xml', CustomerIntegrationPresets::nestedXmlOrders($serviceCode));

        $schmidt = $this->customer($ikea->organization_id, 'schmidt', 'SCHMIDT');
        $pull = $this->import($schmidt, 'Commandes REST (pull horaire)', 'rest_api', 'json', CustomerIntegrationPresets::apiJsonOrders($serviceCode));
        $this->pullConnection($pull);

        $this->statusPush($this->customer($ikea->organization_id, 'getaz', 'GETAZ'));
    }

    private function customer(string $organizationId, string $code, string $name): Customer
    {
        return Customer::firstOrCreate(
            ['organization_id' => $organizationId, 'code' => $code],
            ['name' => $name, 'status' => CustomerStatus::ACTIVE, 'tracking_enabled' => true],
        );
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function import(Customer $customer, string $name, string $sourceType, string $format, array $mapping): CustomerImportConfiguration
    {
        return CustomerImportConfiguration::updateOrCreate(
            ['customer_id' => $customer->id, 'name' => $name],
            ['source_type' => $sourceType, 'file_format' => $format, 'mapping' => $mapping, 'validation_rules' => self::RULES, 'is_active' => true],
        );
    }

    /** La connexion du pull, désactivée : à activer avec une vraie adresse et un secret. */
    private function pullConnection(CustomerImportConfiguration $configuration): void
    {
        app(RestPullConnections::class)->save($configuration->load('customer'), [
            'baseUrl' => 'https://api.schmidt.example.invalid',
            'authType' => 'bearer',
            'isActive' => false,
            'settings' => [
                'path' => '/v1/orders',
                'recordsPath' => 'data',
                'since' => ['param' => 'updated_since'],
                'pagination' => ['type' => 'page', 'param' => 'page', 'sizeParam' => 'per_page', 'size' => 100],
                'actorUserId' => User::where('email', 'admin@tricolis.dev')->value('id'),
            ],
        ], false, null);
    }

    /** La destination des statuts, désactivée : aucun envoi ne part en démonstration. */
    private function statusPush(Customer $customer): void
    {
        CustomerExportConfiguration::updateOrCreate(
            ['customer_id' => $customer->id, 'name' => 'Statuts de commande (REST)'],
            [
                'export_type' => OrderStatusExportContent::EXPORT_TYPE,
                'format' => ExportFormat::JSON,
                'transport' => ExportTransport::REST_API,
                'host' => 'https://api.getaz.example.invalid',
                'frequency' => OrderStatusExportContent::ON_STATUS_CHANGED,
                'encoding' => 'UTF-8',
                'settings' => ['endpointPath' => '/tricolis/order-status', 'method' => 'POST', 'authType' => 'bearer', 'retry' => ['maxAttempts' => 3, 'backoffSeconds' => 300]],
                'is_active' => false,
            ],
        );
    }
}
