<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Tours\Models\Tour;

/**
 * Le pool « à planifier ».
 *
 * Ce n'est pas une table mais une lecture des commandes dont au moins un
 * service attend une tournée. L'éligibilité est celle qu'applique la
 * planification : deux définitions divergeraient, et l'écran proposerait des
 * services que le serveur refuse.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);

    $this->orderWith = function (array $statuses, ?string $date = null): Order {
        $order = Order::factory()->forOrganization($this->organization)->create();
        $address = Address::factory()->create();

        foreach ($statuses as $status) {
            OrderService::factory()->create([
                'order_id' => $order->id,
                'address_id' => $address->id,
                'status' => $status,
                'requested_date' => $date ?? '2026-09-01',
            ]);
        }

        return $order;
    };

    $this->pool = fn (string $query = '') => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->getJson('/api/v1/planning/pool'.$query);
});

it('lists an order that still has something to plan', function (): void {
    $order = ($this->orderWith)(['ready_to_plan', 'completed']);

    $response = ($this->pool)()->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $order->id);

    expect($row)->not->toBeNull();
    // Seuls les services restants sont rendus : montrer les autres laisserait
    // croire qu'on peut les glisser.
    expect($row['serviceCount'])->toBe(1);
    expect($row['services'])->toHaveCount(1);
});

it('leaves out an order whose services are all done', function (): void {
    $order = ($this->orderWith)(['completed', 'cancelled']);

    $ids = collect(($this->pool)()->assertOk()->json('data'))->pluck('id');

    expect($ids)->not->toContain($order->id);
});

/** Un service déjà porté par une tournée n'attend plus rien. */
it('leaves out a service already assigned', function (): void {
    $order = ($this->orderWith)(['ready_to_plan']);
    $tour = Tour::factory()->forAgency($this->agency)->create(['status' => 'draft']);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$tour->id}/plan", ['orderIds' => [$order->id]])
        ->assertOk();

    $ids = collect(($this->pool)()->assertOk()->json('data'))->pluck('id');

    expect($ids)->not->toContain($order->id);
});

it('filters on the requested date', function (): void {
    $soon = ($this->orderWith)(['ready_to_plan'], '2026-09-01');
    $later = ($this->orderWith)(['ready_to_plan'], '2026-12-24');

    $ids = collect(($this->pool)('?requestedDate=2026-09-01')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($soon->id);
    expect($ids)->not->toContain($later->id);
});

it('gives the totals of what remains to plan', function (): void {
    $order = Order::factory()->forOrganization($this->organization)->create();
    $address = Address::factory()->create();

    OrderService::factory()->create([
        'order_id' => $order->id, 'address_id' => $address->id, 'status' => 'ready_to_plan',
        'weight' => 120.5, 'volume' => 2.5, 'package_count' => 3, 'requested_date' => '2026-09-01',
    ]);
    OrderService::factory()->create([
        'order_id' => $order->id, 'address_id' => $address->id, 'status' => 'completed',
        'weight' => 900, 'volume' => 9, 'package_count' => 40, 'requested_date' => '2026-09-01',
    ]);

    $row = collect(($this->pool)()->assertOk()->json('data'))->firstWhere('id', $order->id);

    // Le service acheve ne pese plus : la commande n'apporte que le reste.
    expect($row['totalWeight'])->toBe(120.5);
    expect($row['totalPackages'])->toBe(3);
    expect($row['earliestRequestedDate'])->toBe('2026-09-01');
});

it('hides the orders of other organizations', function (): void {
    $foreign = Order::factory()->create();
    OrderService::factory()->create([
        'order_id' => $foreign->id,
        'address_id' => Address::factory()->create()->id,
        'status' => 'ready_to_plan',
    ]);

    $ids = collect(($this->pool)()->assertOk()->json('data'))->pluck('id');

    expect($ids)->not->toContain($foreign->id);
});

it('hides the pool from an account without the tours permission', function (): void {
    // Un membre sans role : il voit l'organisation, pas la planification.
    $membership = OrganizationUser::factory()->forOrganization($this->organization)->create();

    $this->actingAs($membership->user, 'sanctum')
        ->withHeaders($this->headers)
        ->getJson('/api/v1/planning/pool')
        ->assertForbidden();
});
