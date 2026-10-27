<?php

use App\Modules\CustomerPortal\Models\PublicTrackingLink;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Str;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalTracking;

/**
 * Cas critique (§41) : une tournée dessert IKEA et QOQA. Le suivi d'IKEA ne
 * doit rien porter de QOQA — ni adresse, ni référence, ni colis, ni arrêt.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->ikea = CustomerPortalOrders::order('ikea');
    $this->qoqa = CustomerPortalOrders::order('qoqa');
    ['tour' => $this->tour, 'qoqaStop' => $this->qoqaStop] = CustomerPortalTracking::sharedTour();
});

/**
 * Tout ce qui permettrait de reconnaître QOQA dans une réponse.
 *
 * @return list<string>
 */
function qoqaFingerprints(): array
{
    $qoqa = CustomerPortalOrders::order('qoqa');

    return [
        'Secret QOQA Bussigny',
        $qoqa->order_number,
        (string) $qoqa->customer_reference,
        $qoqa->id,
        (string) $qoqa->orderServices()->value('id'),
        test()->qoqaStop->id,
        test()->tour->id,
        (string) test()->tour->tour_number,
        'QOQA',
    ];
}

it('leaks nothing of QOQA into the IKEA tracking', function (): void {
    $body = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson("/api/v1/customer/orders/{$this->ikea->id}/tracking")->assertOk()->getContent();

    foreach (qoqaFingerprints() as $fingerprint) {
        expect($body)->not->toContain($fingerprint);
    }
});

it('leaks nothing of QOQA into the IKEA calendar', function (): void {
    $body = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson('/api/v1/customer/calendar/orders?from='.now()->subDay()->toDateString().'&to='.now()->addDays(7)->toDateString())
        ->assertOk()->getContent();

    foreach (qoqaFingerprints() as $fingerprint) {
        expect($body)->not->toContain($fingerprint);
    }
});

it('leaks nothing of QOQA, nor of the tour, into the public link of IKEA', function (): void {
    $token = Str::random(PublicTrackingLink::TOKEN_LENGTH);
    PublicTrackingLink::create(['order_id' => $this->ikea->id, 'token_hash' => PublicTrackingLink::hashToken($token), 'is_active' => true]);

    $body = $this->getJson("/api/v1/public/tracking/{$token}")->assertOk()->getContent();

    foreach ([...qoqaFingerprints(), $this->ikea->id, 'recipientName', 'driver', 'vehicle', 'price', 'cost'] as $fingerprint) {
        expect($body)->not->toContain($fingerprint);
    }
});

it('exposes the IKEA stop times, not the stop sequence in the tour', function (): void {
    $service = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson("/api/v1/customer/orders/{$this->ikea->id}/tracking")->json('data.services.0');

    expect($service['stop'])->toHaveKeys(['status', 'plannedArrivalAt', 'actualArrivalAt'])
        ->not->toHaveKeys(['sequence', 'tourId', 'address', 'services']);
});
