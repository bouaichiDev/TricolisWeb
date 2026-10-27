<?php

declare(strict_types=1);

use App\Modules\Statuses\Models\Status;
use App\Shared\Database\MorphMap;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * La couleur d'un statut : réglée par la plateforme dans le référentiel, lue
 * par le portail client (calendrier, badges) — jamais écrite en dur.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->headers = ['X-Organization-Id' => authOrganization()->id];
    $this->status = Status::where('source', MorphMap::ORDER)->where('code', 'draft')->firstOrFail();
});

it('lets the platform set and clear the color of a status', function (): void {
    makePlatformAdmin($this->user);
    $client = $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers);

    $client->putJson("/api/v1/statuses/{$this->status->id}", ['color' => '#1D4ED8'])
        ->assertOk()
        ->assertJsonPath('data.color', '#1D4ED8');

    $client->putJson("/api/v1/statuses/{$this->status->id}", ['color' => null])
        ->assertOk()
        ->assertJsonPath('data.color', null);
});

it('refuses a color that is not #RRGGBB', function (string $color): void {
    makePlatformAdmin($this->user);

    $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
        ->putJson("/api/v1/statuses/{$this->status->id}", ['color' => $color])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('color');
})->with(['red', '#12345', '#GGGGGG', 'javascript:alert(1)']);

it('gives the color to the customer portal calendar', function (): void {
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->status->update(['color' => '#9333EA']);
    $order = CustomerPortalOrders::order('ikea');
    $order->forceFill(['status' => 'draft'])->saveQuietly();

    $event = collect($this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson('/api/v1/customer/calendar/orders?from='.now()->toDateString().'&to='.now()->addDays(7)->toDateString())
        ->assertOk()->json('data'))->firstWhere('id', $order->id);

    expect($event['extendedProps']['status'])->toMatchArray(['code' => 'draft', 'color' => '#9333EA']);
});
