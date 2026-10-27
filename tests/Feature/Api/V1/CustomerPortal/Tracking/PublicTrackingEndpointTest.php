<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\CustomerPortal\Models\PublicTrackingLink;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tracking\Models\TrackingEvent;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalTracking;

/**
 * API publique du lien de suivi (§26-§31, §39-§40, §44).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->ikea = CustomerPortalOrders::order('ikea');
    $this->token = Str::random(PublicTrackingLink::TOKEN_LENGTH);
    $this->link = PublicTrackingLink::create([
        'order_id' => $this->ikea->id,
        'token_hash' => PublicTrackingLink::hashToken($this->token),
        'is_active' => true,
    ]);
});

function publicTracking(string $token): TestResponse
{
    return test()->getJson("/api/v1/public/tracking/{$token}");
}

it('shows a minimal tracking without any authentication', function (): void {
    $response = publicTracking($this->token)->assertOk()
        ->assertJsonPath('data.reference.reference', $this->ikea->order_number)
        ->assertJsonPath('data.status.code', $this->ikea->status->value)
        ->assertJsonPath('data.map', null)
        ->assertJsonStructure(['data' => ['organization' => ['name', 'hasLogo'], 'visibility', 'progress', 'services', 'timeline', 'eta', 'refreshSeconds']])
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('exposes no internal data', function (): void {
    $body = publicTracking($this->token)->getContent();

    foreach ([$this->ikea->id, $this->link->id, $this->ikea->customer_id, $this->ikea->organization_id, 'price', 'cost', 'invoice', 'remark', 'addressLine1', 'recipientName', 'actor', 'createdBy', 'token'] as $forbidden) {
        expect($body)->not->toContain($forbidden);
    }
});

it('answers the same generic 404 for every unusable token', function (string $case): void {
    $token = match ($case) {
        'unknown' => Str::random(PublicTrackingLink::TOKEN_LENGTH),
        'altered' => substr($this->token, 0, -1).($this->token[-1] === 'a' ? 'b' : 'a'),
        'malformed' => 'not-a-token',
        'order id' => $this->ikea->id,
        'reference' => $this->ikea->order_number,
        default => $this->token,
    };

    match ($case) {
        'revoked' => $this->link->update(['is_active' => false, 'revoked_at' => now()]),
        'disabled' => $this->link->update(['is_active' => false]),
        'expired' => $this->link->update(['expires_at' => now()->subMinute()]),
        'tracking off' => CustomerPortal::customer('ikea')->update(['tracking_enabled' => false]),
        default => null,
    };

    publicTracking($token)->assertNotFound()->assertExactJson(['message' => 'Tracking indisponible', 'code' => 'TRACKING_UNAVAILABLE']);
})->with(['unknown', 'altered', 'malformed', 'order id', 'reference', 'revoked', 'disabled', 'expired', 'tracking off']);

it('stops answering once the order no longer exists', function (): void {
    // Le lien survit ici à sa commande — ce que la cascade empêche en temps
    // normal — pour vérifier que la résolution ne s'y fie pas.
    Schema::withoutForeignKeyConstraints(fn () => DB::table('orders')->where('id', $this->ikea->id)->delete());

    publicTracking($this->token)->assertNotFound()->assertJsonPath('code', 'TRACKING_UNAVAILABLE');
});

it('records a light access trace instead of an audit entry', function (): void {
    publicTracking($this->token)->assertOk();
    publicTracking($this->token)->assertOk();

    $link = $this->link->fresh();

    expect($link->last_access_at)->not->toBeNull()
        ->and($link->access_count)->toBe(1)
        ->and(AuditLog::where('entity_id', $this->link->id)->count())->toBe(0);
});

it('follows the visibility chosen by the carrier', function (): void {
    ['tour' => $tour] = CustomerPortalTracking::sharedTour(TourStatus::IN_PROGRESS);
    TrackingEvent::factory()->create(['organization_id' => $tour->organization_id, 'order_id' => $this->ikea->id, 'tour_id' => $tour->id, 'latitude' => 46.5, 'longitude' => 6.6, 'occurred_at' => now()]);
    $organization = CustomerPortal::customer('ikea')->organization;
    $organization->update(['settings' => [...($organization->settings ?? []), 'publicTracking' => ['showMap' => true, 'showReference' => false, 'showTimeline' => false]]]);

    publicTracking($this->token)->assertOk()
        ->assertJsonPath('data.reference', null)
        ->assertJsonPath('data.timeline', null)
        ->assertJsonPath('data.map.available', true)
        ->assertJsonPath('data.map.currentPosition.latitude', 46.5)
        ->assertJsonPath('data.eta.available', true);
});

it('has its own rate limit', function (): void {
    config(['customer_portal.public_tracking.rate_limit_per_minute' => 3]);

    foreach (range(1, 3) as $attempt) {
        publicTracking('not-a-token')->assertNotFound();
    }

    publicTracking('not-a-token')->assertStatus(429);
});

it('serves no logo when the organization has none', function (): void {
    $this->get("/api/v1/public/tracking/{$this->token}/logo")->assertNotFound();
});
