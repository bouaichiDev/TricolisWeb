<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerDefaultAgency;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\CreateFullOrder;
use App\Modules\Orders\DTOs\CreateOrderData;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\MorphMap;
use Database\Seeders\Support\SwissAddressBook;
use Database\Seeders\Support\SwissCatalogue;
use Illuminate\Database\Seeder;

/**
 * De quoi commander depuis le portail de démonstration : pour IKEA et QOQA,
 * une adresse dans le carnet du client, un catalogue (IKEA seulement) et une
 * première commande passée par le service métier commun.
 *
 * Réservé aux environnements local et de test. Rejouable : rien n'est recréé
 * si le client a déjà ses commandes.
 */
class DemoCustomerPortalOrderSeeder extends Seeder
{
    /** @var array<string, array{address: int, admin: string, articles: list<array{string, string, float}>}> */
    private const array CUSTOMERS = [
        'ikea' => [
            'address' => 3,
            'admin' => 'admin@ikea.test',
            'articles' => [
                ['BILLY-80', 'Bibliothèque Billy 80 cm', 30.5],
                ['KALLAX-4X4', 'Étagère Kallax 4×4', 62.0],
                ['MALM-160', 'Lit Malm 160 cm', 71.0],
            ],
        ],
        'qoqa' => ['address' => 11, 'admin' => 'admin@qoqa.test', 'articles' => []],
    ];

    public function run(SwissCatalogue $catalogue, CreateFullOrder $create): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $organization = Organization::where('code', DevelopmentOrganizationSeeder::ORGANIZATION_CODE)->first();
        $user = User::where('email', self::CUSTOMERS['ikea']['admin'])->first();

        if ($organization === null || $user === null) {
            return;
        }

        $serviceId = $catalogue->deliveryServiceId($organization);

        foreach (self::CUSTOMERS as $code => $definition) {
            $customer = Customer::where('organization_id', $organization->id)->where('code', $code)->first();
            $admin = User::where('email', $definition['admin'])->first();

            if ($customer === null || $admin === null) {
                continue;
            }

            $addressId = $this->address($customer, $definition['address']);
            $items = $this->catalog($customer, $definition['articles']);

            if (! $customer->orders()->exists()) {
                $this->order($create, $customer, $admin, $addressId, $serviceId, $items);
            }
        }
    }

    private function address(Customer $customer, int $index): string
    {
        $address = Address::firstOrCreate(
            ['code' => 'PORTAL-'.strtoupper($customer->code)],
            [...SwissAddressBook::at($index), 'name' => $customer->name.' — entrepôt'],
        );

        EntityAddress::firstOrCreate([
            'organization_id' => $customer->organization_id,
            'address_id' => $address->id,
            'entity_type' => MorphMap::CUSTOMER,
            'entity_id' => $customer->id,
        ], ['address_type' => 'delivery', 'is_default' => true]);

        return $address->id;
    }

    /**
     * @param  list<array{string, string, float}>  $articles
     * @return list<string> identifiants des articles
     */
    private function catalog(Customer $customer, array $articles): array
    {
        if ($articles === []) {
            return [];
        }

        $customer->update(['catalog_enabled' => true]);
        $catalog = CustomerCatalog::firstOrCreate(
            ['customer_id' => $customer->id, 'code' => 'main'],
            ['name' => 'Catalogue '.$customer->name, 'status' => 'active'],
        );

        return array_map(static fn (array $article): string => $catalog->items()->firstOrCreate(
            ['article_code' => $article[0]],
            ['name' => $article[1], 'weight' => $article[2], 'status' => 'active'],
        )->id, $articles);
    }

    /**
     * @param  list<string>  $items
     */
    private function order(CreateFullOrder $create, Customer $customer, User $admin, string $addressId, string $serviceId, array $items): void
    {
        $lines = $items === []
            ? [['name' => 'Colis divers', 'quantity' => 2]]
            : [['catalogItemId' => $items[0], 'quantity' => 1], ['catalogItemId' => $items[1], 'quantity' => 2]];

        $create->execute(CreateOrderData::fromArray([
            'customerId' => $customer->id,
            'agencyId' => app(CustomerDefaultAgency::class)->for($customer),
            'source' => OrderSource::CUSTOMER_PORTAL->value,
            'customerReference' => strtoupper($customer->code).'-DEMO-1',
            'lines' => $lines,
            'services' => [[
                'serviceId' => $serviceId,
                'addressId' => $addressId,
                'serviceNumber' => 'S1',
                'sequence' => 1,
                'requestedDate' => now()->addDays(3)->toDateString(),
                'quantity' => 1,
                'unit' => 'commande',
                'requiredTimeMinutes' => 30,
                'remainingTimeMinutes' => 30,
                'weight' => 0,
                'volume' => 0,
                'packageCount' => 0,
                'contacts' => [['firstName' => 'Réception', 'lastName' => $customer->name, 'contactRole' => 'delivery', 'isPrimary' => true]],
            ]],
        ]), $customer->organization_id, $admin);
    }
}
