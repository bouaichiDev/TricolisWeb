<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;
use App\Modules\Tours\Models\TourStopService;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\DB;

/**
 * Commandes de démonstration du portail et payloads valides, tels que
 * `DemoCustomerPortalOrderSeeder` permet de les construire.
 */
final class CustomerPortalOrders
{
    public static function order(string $customerCode): Order
    {
        return Order::where('customer_id', CustomerPortal::customer($customerCode)->id)->oldest()->firstOrFail();
    }

    public static function addressOf(string $customerCode): string
    {
        return (string) DB::table('entity_addresses')
            ->where('entity_type', MorphMap::CUSTOMER)
            ->where('entity_id', CustomerPortal::customer($customerCode)->id)
            ->value('address_id');
    }

    public static function article(string $customerCode, int $index = 0): string
    {
        return CustomerCatalogItem::whereHas('catalog', static fn ($query) => $query
            ->where('customer_id', CustomerPortal::customer($customerCode)->id))
            ->orderBy('article_code')->skip($index)->firstOrFail()->id;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        $customer = CustomerPortal::customer('ikea');

        return array_replace([
            'customerReference' => 'IKEA-4587',
            'workerRemark' => 'Sonner à l’interphone.',
            'lines' => [
                ['catalogItemId' => self::article('ikea'), 'quantity' => 2],
                ['catalogItemId' => self::article('ikea', 1), 'quantity' => 1],
            ],
            'packages' => [
                ['key' => 'P1', 'weight' => 40, 'lines' => [['lineKey' => '0', 'quantity' => 2]]],
            ],
            'services' => [[
                'serviceId' => Service::where('organization_id', $customer->organization_id)->where('code', 'DELIVERY')->value('id'),
                'addressId' => self::addressOf('ikea'),
                'requestedDate' => now()->addDays(5)->toDateString(),
                'requestedFrom' => '08:00',
                'requestedTo' => '12:00',
                'instructions' => 'Livraison au quai 3.',
                'contacts' => [['firstName' => 'Anna', 'lastName' => 'Berg', 'phone' => '+41 21 000 00 00']],
                'packages' => [['packageKey' => 'P1']],
            ]],
        ], $overrides);
    }

    /** Place un service de la commande sur une tournée : elle devient « planifiée ». */
    public static function plan(Order $order): void
    {
        TourStopService::factory()->create([
            'order_service_id' => $order->orderServices()->value('id'),
            'is_active_assignment' => true,
        ]);
    }
}
