<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;
use App\Modules\Planning\Actions\EnsureLoadingService;
use App\Modules\Planning\Services\DepotAddress;
use App\Modules\Planning\Services\LoadingServices;
use App\Modules\Tours\Models\Tour;

/**
 * Le chargement créé au moment de planifier.
 *
 * Une livraison sans chargement décrit un camion qui part chargé sans que
 * personne ne l'ait chargé : le temps de quai n'apparaît nulle part et la
 * tournée s'annonce plus courte qu'elle ne sera.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->depot = Depot::factory()->create(['agency_id' => $this->agency->id]);
    $this->depotAddress = Address::factory()->create();

    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $this->depotAddress->id,
        'entity_type' => 'depot',
        'entity_id' => $this->depot->id,
        'address_type' => 'operations',
        'is_default' => true,
    ]);

    $this->loadingService = Service::factory()->create([
        'organization_id' => $this->organization->id,
        'code' => 'LOAD',
        'default_duration_minutes' => 25,
    ]);

    /** Active l'option, et déclare le service reconnu comme chargement. */
    $this->configure = function (bool $enabled, bool $declareCode = true): void {
        $settings = ['planning' => [
            'autoCreateLoadingService' => $enabled,
            'loadingServiceCodes' => $declareCode ? ['LOAD'] : [],
        ]];

        $this->organization->forceFill(['settings' => $settings])->save();
        $this->organization->refresh();
    };

    $this->tourWith = fn (?Depot $depot): Tour => Tour::factory()->forAgency($this->agency)
        ->create(['status' => 'draft', 'depot_id' => $depot?->id]);

    $this->delivery = function (): Order {
        $order = Order::factory()->forOrganization($this->organization)->create([
            'agency_id' => $this->agency->id,
        ]);

        OrderService::factory()->create([
            'order_id' => $order->id,
            'address_id' => Address::factory()->create()->id,
            'status' => 'ready_to_plan',
            'sequence' => 1,
        ]);

        return $order;
    };

    $this->plan = fn (Tour $tour, Order $order) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$tour->id}/plan", ['orderIds' => [$order->id]]);
});

describe('option active', function (): void {
    it('crée le chargement manquant et le planifie', function (): void {
        ($this->configure)(true);
        $tour = ($this->tourWith)($this->depot);
        $order = ($this->delivery)();

        $response = ($this->plan)($tour, $order)->assertOk();

        // Deux services planifiés : la livraison glissée, et le chargement né
        // du réglage.
        expect($response->json('data.planned'))->toHaveCount(2);

        $loading = OrderService::where('order_id', $order->id)
            ->where('service_id', $this->loadingService->id)
            ->firstOrFail();

        expect($loading->address_id)->toBe($this->depotAddress->id)
            ->and($loading->required_time_minutes)->toBe(25)
            ->and($loading->sequence)->toBe(2)
            ->and($loading->service_number)->toBe("{$order->order_number}-S2");
    });

    /**
     * Le numéro de prestation dérive du rang. Renuméroter les services
     * existants changerait des références déjà communiquées au client ; ce qui
     * fait charger en premier, c'est l'arrêt du dépôt remonté en tête.
     */
    it('ouvre la tournée par l’arrêt du dépôt', function (): void {
        ($this->configure)(true);
        $tour = ($this->tourWith)($this->depot);

        ($this->plan)($tour, ($this->delivery)())->assertOk();

        expect($tour->stops()->orderBy('sequence')->first()->address_id)
            ->toBe($this->depotAddress->id);
    });

    it('n’en crée pas un second quand la commande en porte déjà un', function (): void {
        ($this->configure)(true);
        $tour = ($this->tourWith)($this->depot);
        $order = ($this->delivery)();

        OrderService::factory()->create([
            'order_id' => $order->id,
            'service_id' => $this->loadingService->id,
            'address_id' => $this->depotAddress->id,
            'status' => 'ready_to_plan',
            'sequence' => 2,
        ]);

        ($this->plan)($tour, $order)->assertOk();

        expect(OrderService::where('order_id', $order->id)
            ->where('service_id', $this->loadingService->id)->count())->toBe(1);
    });

    /**
     * Le refus porte sur toute la commande : planifier la livraison en
     * annonçant que le chargement a échoué laisserait une tournée qu'on croit
     * complète et qui ne l'est pas.
     */
    it('refuse la commande entière quand la tournée n’a pas de dépôt', function (): void {
        ($this->configure)(true);
        $tour = ($this->tourWith)(null);
        $order = ($this->delivery)();

        $response = ($this->plan)($tour, $order)->assertOk();

        expect($response->json('data.planned'))->toBe([])
            ->and($response->json('data.rejected.0.reason'))
            ->toBe(EnsureLoadingService::REASON_NO_DEPOT)
            ->and($tour->stops()->count())->toBe(0);
    });

    /** L'option promet un chargement ; sans code déclaré, elle ne peut pas tenir. */
    it('refuse quand aucun service n’est déclaré comme chargement', function (): void {
        ($this->configure)(true, false);
        $tour = ($this->tourWith)($this->depot);

        $response = ($this->plan)($tour, ($this->delivery)())->assertOk();

        expect($response->json('data.rejected.0.reason'))
            ->toBe(EnsureLoadingService::REASON_NO_LOADING_SERVICE);
    });
});

describe('option inactive', function (): void {
    /**
     * Toutes les organisations ne travaillent pas ainsi : un transporteur
     * d'enlèvements n'a pas de quai à ouvrir, et lui imposer un chargement
     * fictif fausserait ses tournées dans l'autre sens.
     */
    it('planifie la livraison seule, sans rien créer', function (): void {
        ($this->configure)(false);
        $tour = ($this->tourWith)($this->depot);
        $order = ($this->delivery)();

        expect(($this->plan)($tour, $order)->assertOk()->json('data.planned'))->toHaveCount(1);
        expect(OrderService::where('order_id', $order->id)->count())->toBe(1);
    });

    it('n’exige pas de dépôt', function (): void {
        ($this->configure)(false);

        expect(($this->plan)(($this->tourWith)(null), ($this->delivery)())->assertOk()
            ->json('data.planned'))->toHaveCount(1);
    });
});

/** Le réglage se lit là où il vit, et nulle part ailleurs. */
it('lit l’option dans les réglages de l’organisation', function (): void {
    $action = new EnsureLoadingService(app(LoadingServices::class), app(DepotAddress::class));

    ($this->configure)(false);
    expect($action->isEnabled($this->organization))->toBeFalse();

    ($this->configure)(true);
    expect($action->isEnabled($this->organization))->toBeTrue();
});
