<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Addresses\Models\Address;
use App\Modules\CustomerPortal\Models\CustomerUserRole;
use App\Modules\Identity\Models\Role;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;

/**
 * Tournées de démonstration pour le suivi : une tournée qui dessert IKEA **et**
 * QOQA, le cas que la Phase 3 impose de tester (§41).
 */
final class CustomerPortalTracking
{
    /**
     * Une tournée partagée : un arrêt pour la commande IKEA, un pour la
     * commande QOQA, chacun à une adresse reconnaissable.
     *
     * @param  array<string, mixed>  $ikeaStop
     * @return array{tour: Tour, ikeaStop: TourStop, qoqaStop: TourStop}
     */
    public static function sharedTour(TourStatus $status = TourStatus::IN_PROGRESS, array $ikeaStop = []): array
    {
        $organization = Organization::findOrFail(CustomerPortal::customer('ikea')->organization_id);
        $tour = Tour::factory()->forOrganization($organization)->status($status)->create([
            'tour_date' => now()->toDateString(),
            'actual_start_at' => $status === TourStatus::IN_PROGRESS ? now()->subHour() : null,
        ]);

        return [
            'tour' => $tour,
            'ikeaStop' => self::stop($tour, CustomerPortalOrders::order('ikea'), 'Entrepôt IKEA Aubonne', 1, $ikeaStop),
            'qoqaStop' => self::stop($tour, CustomerPortalOrders::order('qoqa'), 'Secret QOQA Bussigny', 2, [
                'planned_arrival_at' => now()->addHours(3),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function stop(Tour $tour, Order $order, string $addressName, int $sequence, array $attributes = []): TourStop
    {
        $address = Address::factory()->create(['name' => $addressName, 'city' => $addressName, 'latitude' => 46.5, 'longitude' => 6.6]);
        $stop = TourStop::factory()->forTour($tour)->atSequence($sequence)->create([
            'address_id' => $address->id,
            'planned_arrival_at' => now()->addHours(2),
            ...$attributes,
        ]);

        TourStopService::factory()->forStop($stop)->create([
            'order_service_id' => $order->orderServices()->value('id'),
            'is_active_assignment' => true,
        ]);

        return $stop;
    }

    /** Donne au membre un autre rôle du portail, par code. */
    public static function giveRole(string $email, string $roleCode): void
    {
        $member = CustomerPortal::member($email);
        $role = Role::where('organization_id', $member->customer->organization_id)->where('code', $roleCode)->firstOrFail();

        CustomerUserRole::where('customer_user_id', $member->id)->update(['role_id' => $role->id]);
    }
}
