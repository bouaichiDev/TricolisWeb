<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Enums\ExportTransport;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Services\Content\OrderStatusExportContent;
use App\Modules\Exports\Services\RemoteTargetGuard;
use App\Modules\Integrations\DTOs\InboundFileConnection;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Sources\InboundFilesystems;
use App\Shared\Support\Secret;
use Database\Seeders\Support\CustomerIntegrationPresets;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Outils des tests d'intégration (Phase 6 portail) : configurations posées
 * sur les clients de démonstration, contenus XML/JSON, serveur SFTP simulé.
 *
 * Aucun réseau : le serveur FTP/SFTP est un disque local simulé, les API
 * passent par `Http::fake()`.
 */
final class CustomerIntegrations
{
    public const string SERVICE = 'DELIVERY';

    public const string RECIPIENT_XML = '<Recipient><FirstName>Amina</FirstName><LastName>Alaoui</LastName><Email>amina@example.test</Email><Phone>+41 22 555 01 01</Phone><Street>12 rue du Rhône</Street><PostalCode>1204</PostalCode><City>Genève</City><Country>CH</Country></Recipient>';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function import(string $customerCode, string $preset, array $attributes = []): CustomerImportConfiguration
    {
        $mapping = match ($preset) {
            'xml' => CustomerIntegrationPresets::nestedXmlOrders(self::SERVICE),
            'json' => CustomerIntegrationPresets::apiJsonOrders(self::SERVICE),
            default => CustomerIntegrationPresets::flatSpreadsheetOrders(self::SERVICE),
        };

        return CustomerImportConfiguration::factory()->forCustomer(CustomerPortal::customer($customerCode))->create([
            'name' => "Import {$preset} ".uniqid(),
            'source_type' => ['xml' => 'sftp', 'json' => 'rest_api'][$preset] ?? 'upload',
            'file_format' => ['xml' => 'xml', 'json' => 'json'][$preset] ?? 'xlsx',
            'mapping' => $mapping,
            'validation_rules' => ['externalReference' => ['required', 'max:64']],
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $attributes
     */
    public static function statusPush(Customer $customer, array $settings = [], array $attributes = []): CustomerExportConfiguration
    {
        return CustomerExportConfiguration::factory()->forCustomer($customer)->create([
            'name' => 'Statuts REST '.uniqid(),
            'export_type' => OrderStatusExportContent::EXPORT_TYPE,
            'format' => ExportFormat::JSON,
            'transport' => ExportTransport::REST_API,
            'host' => 'https://partner.example.com',
            'frequency' => OrderStatusExportContent::ON_STATUS_CHANGED,
            'settings' => ['endpointPath' => '/tricolis/status', 'authType' => 'bearer', ...$settings],
            ...$attributes,
        ]);
    }

    /**
     * Une commande XML, avec ses articles.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $items  [sku, désignation, quantité]
     */
    public static function xmlOrder(string $reference, array $items, string $date = '2026-10-05'): string
    {
        $lines = implode('', array_map(
            static fn (array $item): string => "<Item><Sku>{$item[0]}</Sku><Description>{$item[1]}</Description><Quantity>{$item[2]}</Quantity></Item>",
            $items,
        ));

        return "<Order><OrderNo>{$reference}</OrderNo><OrderDate>{$date}</OrderDate><DeliveryDate>{$date}</DeliveryDate>"
            .self::RECIPIENT_XML."<Items>{$lines}</Items></Order>";
    }

    public static function xmlFile(string ...$orders): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Orders>'.implode('', $orders).'</Orders>';
    }

    /**
     * Une commande telle qu'une API de commandes la renverrait.
     *
     * @return array<string, mixed>
     */
    public static function apiOrder(string $reference, string $quantity = '2'): array
    {
        return [
            'reference' => $reference,
            'created_at' => '2026-10-05T08:00:00+02:00',
            'delivery_date' => '2026-10-07',
            'lines' => [['sku' => 'SKU-1', 'label' => 'Armoire', 'qty' => $quantity]],
            'recipient' => [
                'first_name' => 'Amina', 'last_name' => 'Alaoui', 'email' => 'amina@example.test',
                'phone' => '+41 22 555 01 01', 'street' => '12 rue du Rhône', 'zip' => '1204',
                'city' => 'Genève', 'country' => 'CH',
            ],
        ];
    }

    /**
     * Remplace la résolution DNS de la garde SSRF : aucun serveur DNS réel
     * n'est interrogé. Un nom absent de la table résout vers une adresse
     * publique.
     *
     * @param  array<string, list<string>>  $map
     */
    public static function offlineDns(array $map = []): void
    {
        app()->instance(RemoteTargetGuard::class, new RemoteTargetGuard(
            static fn (string $host): array => $map[$host] ?? ['8.8.8.8'],
        ));
    }

    /**
     * Enregistre une connexion de relève telle que le back-office la stocke,
     * sans contrôle d'hôte : c'est à la relève de refuser un hôte interne.
     *
     * @param  array<string, mixed>  $declared  `password` compris, en clair
     */
    public static function fileConnection(CustomerImportConfiguration $configuration, array $declared): void
    {
        $password = $declared['password'] ?? null;
        unset($declared['password'], $declared['driver']);

        $configuration->forceFill([
            'connection' => $declared === [] ? null : $declared,
            'encrypted_password' => Secret::encrypt($password),
        ])->save();
    }

    /**
     * Déclare une relève SFTP pour la configuration, et rend le disque simulé
     * qui joue le serveur.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function fakeSftp(CustomerImportConfiguration $configuration, array $overrides = []): Filesystem
    {
        self::fileConnection($configuration, [
            'host' => 'sftp.partner.example.com', 'port' => 22,
            'username' => 'tricolis', 'password' => 'mot-de-passe-sftp-secret',
            'directory' => 'outbox', 'filePattern' => '*.xml', 'afterImport' => 'archive',
            'archiveDirectory' => 'outbox/archive', 'actorEmail' => 'admin@tricolis.dev',
            ...$overrides,
        ]);

        $disk = Storage::fake('inbound-sftp');

        app()->instance(InboundFilesystems::class, new class($disk) extends InboundFilesystems
        {
            public function __construct(private readonly Filesystem $disk) {}

            public function open(InboundFileConnection $connection): Filesystem
            {
                return $this->disk;
            }
        });

        return $disk;
    }
}
