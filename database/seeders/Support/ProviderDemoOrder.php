<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerDefaultAgency;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\CreateFullOrder;
use App\Modules\Orders\DTOs\CreateOrderData;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Database\MorphMap;

/**
 * La commande des démonstrations du portail fournisseur.
 *
 * Passée par le service métier commun (`CreateFullOrder`), pour un client
 * **sans accès au portail client** : les parcours du portail client suivent
 * leurs propres commandes, qui ne doivent pas se retrouver dans une tournée.
 * Rejouable : la commande `PROVIDER-DEMO-1` n'est créée qu'une fois.
 */
final readonly class ProviderDemoOrder
{
    public const string REFERENCE = 'PROVIDER-DEMO-1';

    public function __construct(private SwissCatalogue $catalogue, private CreateFullOrder $create) {}

    public function service(Organization $organization): ?OrderService
    {
        $existing = Order::where('organization_id', $organization->id)->where('customer_reference', self::REFERENCE)->first();

        if ($existing !== null) {
            return $existing->orderServices()->orderBy('sequence')->first();
        }

        $customer = Customer::where('organization_id', $organization->id)
            ->whereNotIn('id', CustomerUser::query()->select('customer_id'))
            ->orderBy('code')
            ->first();
        $creator = User::whereKey(OrganizationUser::where('organization_id', $organization->id)->orderBy('id')->value('user_id'))->first();

        if ($customer === null || $creator === null) {
            return null;
        }

        $order = $this->create->execute(CreateOrderData::fromArray([
            'customerId' => $customer->id,
            'agencyId' => app(CustomerDefaultAgency::class)->for($customer),
            'source' => OrderSource::INTERNAL->value,
            'customerReference' => self::REFERENCE,
            'lines' => [['name' => 'Colis divers', 'quantity' => 2]],
            'services' => [[
                'serviceId' => $this->catalogue->deliveryServiceId($organization),
                'addressId' => $this->address($customer),
                'serviceNumber' => 'S1',
                'sequence' => 1,
                'requestedDate' => now()->addDay()->toDateString(),
                'quantity' => 1,
                'unit' => 'commande',
                'requiredTimeMinutes' => 30,
                'remainingTimeMinutes' => 30,
                'weight' => 0,
                'volume' => 0,
                'packageCount' => 2,
                'contacts' => [['firstName' => 'Réception', 'lastName' => $customer->name, 'contactRole' => 'delivery', 'isPrimary' => true]],
            ]],
        ]), $organization->id, $creator);

        return $order->orderServices()->orderBy('sequence')->first();
    }

    private function address(Customer $customer): string
    {
        $address = Address::firstOrCreate(
            ['code' => 'PROVIDER-DEMO-'.strtoupper($customer->code)],
            [...SwissAddressBook::at(7), 'name' => $customer->name.' — quai de réception'],
        );

        EntityAddress::firstOrCreate([
            'organization_id' => $customer->organization_id,
            'address_id' => $address->id,
            'entity_type' => MorphMap::CUSTOMER,
            'entity_id' => $customer->id,
        ], ['address_type' => 'delivery', 'is_default' => false]);

        return $address->id;
    }
}
