<?php

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStopService;
use Database\Seeders\CustomerPortalDemoSeeder;
use Database\Seeders\DemoProviderPortalFleetSeeder;
use Database\Seeders\DemoProviderPortalSeeder;
use Database\Seeders\DemoProviderPortalTourSeeder;

/**
 * Les tournées de démonstration des parcours Playwright : rejouables, et
 * remises à leur état de départ à chaque passage.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->seed(DemoProviderPortalSeeder::class);
    $this->seed(DemoProviderPortalFleetSeeder::class);
    $this->seed(DemoProviderPortalTourSeeder::class);
});

it('sème une tournée en cours, arrêt atteint, avec un service confirmé d’une commande existante', function (): void {
    $tour = Tour::where('tour_number', 'DEMO-ALPHA-3')->firstOrFail();
    $stop = $tour->stops()->firstOrFail();
    $line = TourStopService::where('tour_stop_id', $stop->id)->firstOrFail();

    expect($tour->status)->toBe(TourStatus::IN_PROGRESS)
        ->and($stop->status)->toBe(TourStopStatus::ARRIVED)
        ->and($line->confirmed_at)->not->toBeNull()
        ->and($line->orderService->order->organization_id)->toBe($tour->organization_id)
        // Jamais une commande que suivent les démonstrations du portail client.
        ->and(CustomerUser::where('customer_id', $line->orderService->order->customer_id)->exists())->toBeFalse();
});

it('remet les tournées d’exécution à leur état de départ au rejeu', function (): void {
    $executed = Tour::where('tour_number', 'DEMO-ALPHA-2')->firstOrFail();
    $executed->update(['status' => TourStatus::COMPLETED, 'actual_start_at' => now()]);
    $executed->stops()->update(['status' => TourStopStatus::COMPLETED->value]);
    $stop = Tour::where('tour_number', 'DEMO-ALPHA-3')->firstOrFail()->stops()->firstOrFail();
    $line = TourStopService::where('tour_stop_id', $stop->id)->firstOrFail();
    ProofOfDelivery::create(['order_id' => $line->orderService->order_id, 'order_service_id' => $line->order_service_id, 'tour_stop_id' => $stop->id, 'recipient_name' => 'R', 'delivered_at' => now()]);
    $lines = TourStopService::count();

    $this->seed(DemoProviderPortalTourSeeder::class);

    expect($executed->fresh()->status)->toBe(TourStatus::CONFIRMED)
        ->and($executed->stops()->pluck('status')->map->value->unique()->all())->toBe(['pending'])
        ->and(ProofOfDelivery::where('tour_stop_id', $stop->id)->count())->toBe(0)
        ->and(TourStopService::count())->toBe($lines);
});

it('sème des décomptes dont les totaux viennent du module financier, sans doublon au rejeu', function (): void {
    $closed = ProviderSettlement::where('settlement_number', 'DEMO-STL-ALPHA-1')->firstOrFail();
    $count = ProviderSettlementLine::count();

    $this->seed(DemoProviderPortalTourSeeder::class);

    expect($closed->status)->toBe('closed')
        ->and((string) $closed->total)->toBe('240.00')
        ->and(ProviderSettlement::where('settlement_number', 'DEMO-STL-ALPHA-2')->value('status'))->toBe('draft')
        ->and(ProviderSettlementLine::count())->toBe($count);
});
