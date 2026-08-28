<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Providers\Models\Provider;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;

/**
 * Ce qu'on doit encore régler à un fournisseur.
 *
 * Le §17 pose le vrai problème : un service peut avoir plusieurs affectations
 * historiques, chez des fournisseurs différents. Payer « le dernier » ferait
 * payer une tentative échouée.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->provider = Provider::factory()->forOrganization($this->organization)->create();
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->service = function (string $status = 'completed', array $o = []): OrderService {
        $order = Order::factory()->forOrganization($this->organization)
            ->create(['customer_id' => $this->customer->id]);

        return OrderService::factory()->create(array_merge([
            'order_id' => $order->id,
            'status' => $status,
        ], $o));
    };

    /** Pose le service sur une tournée du fournisseur donné. */
    $this->assign = function (OrderService $service, ?Provider $provider, bool $active): TourStopService {
        $tour = Tour::factory()->forAgency($this->agency)->create([
            'provider_id' => $provider?->id,
            'status' => 'completed',
        ]);

        $stop = TourStop::factory()->create([
            'tour_id' => $tour->id,
            'address_id' => Address::factory()->create()->id,
            'status' => 'completed',
        ]);

        return TourStopService::factory()->create([
            'tour_stop_id' => $stop->id,
            'order_service_id' => $service->id,
            'is_active_assignment' => $active,
            'status' => $active ? 'planned' : 'replanned',
        ]);
    };

    $this->list = fn (array $query = []) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->getJson("/api/v1/providers/{$this->provider->id}/settleable-services?".http_build_query($query));
});

it('propose une prestation exécutée par ce fournisseur', function (): void {
    $service = ($this->service)();
    ($this->assign)($service, $this->provider, true);

    ($this->list)()->assertOk()->assertJsonPath('data.0.id', $service->id);
});

/**
 * **Le cœur du §17.** Le fournisseur A a tenté et échoué, le fournisseur B a
 * livré. Seul B doit être payé, et l'historique de A ne doit pas le faire
 * remonter.
 */
it('ne règle pas la tentative échouée d’un autre fournisseur', function (): void {
    $failed = Provider::factory()->forOrganization($this->organization)->create();

    $service = ($this->service)();
    ($this->assign)($service, $failed, false);
    ($this->assign)($service, $this->provider, true);

    // Le fournisseur qui a livre le voit.
    ($this->list)()->assertOk()->assertJsonCount(1, 'data');

    // Celui dont l'affectation est historique, non.
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/providers/{$failed->id}/settleable-services")
        ->assertOk()->assertJsonCount(0, 'data');
});

/** Une tournée sans fournisseur : le transporteur roule lui-même, personne à payer. */
it('ne propose rien pour une tournée sans fournisseur', function (): void {
    ($this->assign)(($this->service)(), null, true);

    ($this->list)()->assertOk()->assertJsonCount(0, 'data');
});

/** §16 : un service ne se règle pas deux fois. */
it('écarte ce qui est déjà réglé', function (): void {
    $service = ($this->service)();
    ($this->assign)($service, $this->provider, true);

    $settlement = ProviderSettlement::factory()->create([
        'organization_id' => $this->organization->id,
        'provider_id' => $this->provider->id,
    ]);

    ProviderSettlementLine::factory()->create([
        'settlement_id' => $settlement->id,
        'order_service_id' => $service->id,
    ]);

    ($this->list)()->assertOk()->assertJsonCount(0, 'data');
});

it('écarte ce qui n’est pas terminé', function (): void {
    ($this->assign)(($this->service)('planned'), $this->provider, true);

    ($this->list)()->assertOk()->assertJsonCount(0, 'data');
});

/** §103 : le prix client est montré, mais il n'est pas un coût. */
it('montre le client servi et le prix client, à titre indicatif', function (): void {
    ($this->assign)(($this->service)(), $this->provider, true);

    ($this->list)()->assertOk()->assertJsonStructure(['data' => [[
        'id', 'serviceNumber', 'orderNumber', 'customerName', 'customerUnitPrice', 'address',
    ]]]);
});

it('cache un fournisseur d’une autre organisation', function (): void {
    $foreign = Provider::factory()->create();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/providers/{$foreign->id}/settleable-services")
        ->assertNotFound();
});
