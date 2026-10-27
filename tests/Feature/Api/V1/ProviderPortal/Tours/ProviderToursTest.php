<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use Database\Seeders\DemoProviderPortalSeeder;
use Tests\Support\ProviderPortal;

/**
 * Tournées du portail fournisseur : seulement celles attribuées au fournisseur
 * courant, jamais en brouillon, et sans aucune donnée commerciale.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $this->beta = ProviderPortal::provider('transports-beta');
    $this->org = $this->alpha->organization;
    $make = fn (string $providerId, TourStatus $status, string $number, ?string $date = null) => Tour::factory()->forOrganization($this->org)->create([
        'provider_id' => $providerId, 'status' => $status, 'tour_number' => $number, 'tour_date' => $date ?? '2026-11-03',
        'planned_start_at' => ($date ?? '2026-11-03').' 07:00:00', 'planned_end_at' => ($date ?? '2026-11-03').' 12:00:00',
    ]);
    $this->mine = $make($this->alpha->id, TourStatus::CONFIRMED, 'T-ALPHA-1');
    $this->other = $make($this->alpha->id, TourStatus::PLANNED, 'T-ALPHA-2', '2026-11-20');
    $this->draft = $make($this->alpha->id, TourStatus::DRAFT, 'T-ALPHA-DRAFT');
    $this->theirs = $make($this->beta->id, TourStatus::CONFIRMED, 'T-BETA-1');
    $this->foreign = Tour::factory()->forOrganization(Organization::factory()->create())->create(['provider_id' => $this->alpha->id, 'status' => TourStatus::CONFIRMED]);
    $second = TourStop::factory()->forTour($this->mine)->atSequence(2)->create();
    $first = TourStop::factory()->forTour($this->mine)->atSequence(1)->create();
    $service = OrderService::factory()->state(['order_id' => Order::factory()->state(['organization_id' => $this->org->id]), 'weight' => 12.5, 'package_count' => 3, 'customer_total_price' => 999, 'provider_total_cost' => 444])->create();
    TourStopService::factory()->forStop($first)->create(['order_service_id' => $service->id]);
    TourStopService::factory()->forStop($second)->forOrganization($this->org->id)->inactive()->create();
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
});

/** Toutes les clés d'une réponse JSON, à toute profondeur. */
function jsonKeys(array $data): array
{
    $keys = [];
    foreach ($data as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }
        if (is_array($value)) {
            $keys = [...$keys, ...jsonKeys($value)];
        }
    }

    return array_values(array_unique($keys));
}

it('ne liste que les tournées attribuées au fournisseur, hors brouillons', function (): void {
    $numbers = collect($this->withHeaders($this->admin)->getJson('/api/v1/provider/tours?perPage=100')->assertOk()->json('data'))->pluck('tourNumber');

    expect($numbers->sort()->values()->all())->toBe(['T-ALPHA-1', 'T-ALPHA-2']);
    $this->withHeaders($this->admin)->getJson('/api/v1/provider/tours?from=2026-11-01&to=2026-11-10')->assertOk()->assertJsonCount(1, 'data');
    $this->withHeaders($this->admin)->getJson('/api/v1/provider/tours?status=planned')->assertOk()->assertJsonPath('data.0.tourNumber', 'T-ALPHA-2');
});

it('répond 404 pour une tournée d’un autre fournisseur, d’une autre organisation, en brouillon ou inexistante', function (string $case): void {
    $id = match ($case) {
        'autre fournisseur' => $this->theirs->id, 'autre organisation' => $this->foreign->id, 'brouillon' => $this->draft->id, 'inexistante' => '01JZZZZZZZZZZZZZZZZZZZZZZZ'
    };

    $this->withHeaders($this->admin)->getJson("/api/v1/provider/tours/{$id}")->assertNotFound();
})->with(['autre fournisseur', 'autre organisation', 'brouillon', 'inexistante']);

it('rend le détail opérationnel, arrêts dans l’ordre, sans aucune donnée commerciale', function (): void {
    $response = $this->withHeaders($this->admin)->getJson("/api/v1/provider/tours/{$this->mine->id}")->assertOk()
        ->assertJsonPath('data.tourNumber', 'T-ALPHA-1')
        ->assertJsonPath('data.stops.0.sequence', 1)
        ->assertJsonPath('data.stops.1.sequence', 2)
        ->assertJsonPath('data.stops.0.services.0.packageCount', 3)
        ->assertJsonCount(0, 'data.stops.1.services');

    $forbidden = array_filter(jsonKeys($response->json()), static fn (string $key): bool => (bool) preg_match('/price|cost|invoice|order|customer|reference|external/i', $key));

    expect($forbidden)->toBe([]);
});

it('sert un calendrier borné', function (): void {
    $events = $this->withHeaders($this->admin)->getJson('/api/v1/provider/tours/calendar?from=2026-11-01&to=2026-11-30')->assertOk()->json('data');

    expect(collect($events)->pluck('title')->sort()->values()->all())->toBe(['T-ALPHA-1', 'T-ALPHA-2']);
    $this->withHeaders($this->admin)->getJson('/api/v1/provider/tours/calendar?from=2026-11-30&to=2026-11-01')->assertUnprocessable();
    $this->withHeaders($this->admin)->getJson('/api/v1/provider/tours/calendar?from=2026-01-01&to=2026-12-31')->assertUnprocessable();
});

it('ferme les tournées sans `provider.tours.view`', function (): void {
    Role::where('organization_id', $this->org->id)->where('code', ProviderPortalRoles::VIEWER)->firstOrFail()
        ->permissions()->detach(Permission::where('code', 'provider.tours.view')->value('id'));

    $this->withHeaders(ProviderPortal::headers('viewer@transports-alpha.test'))->getJson('/api/v1/provider/tours')->assertForbidden();
});

it('fait disparaître aussitôt une tournée réattribuée à un autre fournisseur', function (): void {
    $this->withHeaders($this->admin)->getJson("/api/v1/provider/tours/{$this->mine->id}")->assertOk();
    $this->mine->update(['provider_id' => $this->beta->id]);

    $this->withHeaders(ProviderPortal::headers('admin@transports-alpha.test'))->getJson("/api/v1/provider/tours/{$this->mine->id}")->assertNotFound();
});
