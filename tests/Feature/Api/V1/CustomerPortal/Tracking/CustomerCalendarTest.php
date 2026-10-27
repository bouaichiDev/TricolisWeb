<?php

use App\Modules\CustomerPortal\Support\CustomerPortalRoles;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalTracking;

/**
 * Calendrier du portail (§42) : les commandes du client, et d'aucun autre,
 * sur la période demandée.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->ikea = CustomerPortalOrders::order('ikea');
    $this->qoqa = CustomerPortalOrders::order('qoqa');
    // Les commandes de démonstration sont demandées à J+3.
    $this->period = ['from' => now()->toDateString(), 'to' => now()->addDays(7)->toDateString()];
});

function portalCalendar(array $query, string $email = 'admin@ikea.test'): TestResponse
{
    return test()->withHeaders(CustomerPortal::headers($email))->getJson('/api/v1/customer/calendar/orders?'.http_build_query($query));
}

it('returns only the orders of the current customer in the period', function (): void {
    $response = portalCalendar($this->period)->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($this->ikea->id)->not->toContain($this->qoqa->id)
        ->and($response->json('meta.truncated'))->toBeFalse()
        ->and($response->json('meta.filters.statuses'))->not->toBeEmpty()
        ->and($response->json('meta.filters.services'))->not->toBeEmpty();
});

it('formats an event for FullCalendar from the requested date', function (): void {
    $event = collect(portalCalendar($this->period)->json('data'))->firstWhere('id', $this->ikea->id);

    expect($event['title'])->toBe($this->ikea->order_number)
        ->and($event['start'])->toBe(now()->addDays(3)->toDateString())
        ->and($event['allDay'])->toBeTrue()
        ->and($event['extendedProps']['slotSource'])->toBe('requested')
        ->and($event['extendedProps']['serviceCount'])->toBe(1)
        ->and($event['extendedProps']['status'])->toHaveKeys(['code', 'label'])
        ->and($event['extendedProps']['actions'])->toHaveKeys(['canView', 'canEdit', 'canTrack']);
});

it('respects the period boundaries', function (): void {
    $later = ['from' => now()->addDays(10)->toDateString(), 'to' => now()->addDays(20)->toDateString()];

    expect(portalCalendar($later)->assertOk()->json('data'))->toBeEmpty();
});

it('prefers the planned arrival of the tour stop over the requested date', function (): void {
    $planned = now()->addDay()->setTime(9, 30);
    CustomerPortalTracking::sharedTour(ikeaStop: ['planned_arrival_at' => $planned, 'planned_departure_at' => $planned->copy()->addHour()]);

    $event = collect(portalCalendar($this->period)->json('data'))->firstWhere('id', $this->ikea->id);

    expect($event['start'])->toBe($planned->format('Y-m-d\TH:i:s'))
        ->and($event['allDay'])->toBeFalse()
        ->and($event['extendedProps']['slotSource'])->toBe('planned');
});

it('filters by status, service and search', function (): void {
    $status = $this->ikea->status->value;
    $serviceId = $this->ikea->orderServices()->value('service_id');

    expect(portalCalendar([...$this->period, 'status' => [$status]])->json('data'))->toHaveCount(1)
        ->and(portalCalendar([...$this->period, 'status' => ['cancelled']])->json('data'))->toBeEmpty()
        ->and(portalCalendar([...$this->period, 'service' => [$serviceId]])->json('data'))->toHaveCount(1)
        ->and(portalCalendar([...$this->period, 'search' => $this->ikea->order_number])->json('data'))->toHaveCount(1)
        ->and(portalCalendar([...$this->period, 'search' => $this->qoqa->order_number])->json('data'))->toBeEmpty();
});

it('computes the actions from the member permissions', function (): void {
    $viewer = collect(portalCalendar($this->period, 'viewer@ikea.test')->json('data'))->firstWhere('id', $this->ikea->id);
    $manager = collect(portalCalendar($this->period, 'orders@ikea.test')->json('data'))->firstWhere('id', $this->ikea->id);

    expect($viewer['extendedProps']['actions']['canEdit'])->toBeFalse()
        ->and($viewer['extendedProps']['actions']['canTrack'])->toBeTrue()
        ->and($manager['extendedProps']['actions']['canEdit'])->toBeTrue();
});

it('requires the calendar permission', function (): void {
    CustomerPortalTracking::giveRole('viewer@ikea.test', CustomerPortalRoles::STOCK_MANAGER);

    portalCalendar($this->period, 'viewer@ikea.test')->assertForbidden();
});

it('requires a bounded period', function (): void {
    portalCalendar([])->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
    portalCalendar(['from' => '2026-09-10', 'to' => '2026-09-01'])->assertUnprocessable()->assertJsonValidationErrors(['to']);
    portalCalendar(['from' => '2026-01-01', 'to' => '2026-12-31'])->assertUnprocessable()->assertJsonValidationErrors(['to']);
});
