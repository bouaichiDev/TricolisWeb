<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourStop;

/**
 * Ce qui sépare deux arrêts, rendu avec eux.
 *
 * La colonne de planification aligne des arrêts ; sans le trajet qui les relie,
 * deux adresses de la même rue et deux villes distantes d'une heure s'y
 * ressemblent. Le trajet est une période de conduite, et il porte l'arrêt vers
 * lequel il mène : c'est ce qui permet de le glisser avant le bon arrêt.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->tour = Tour::factory()->forAgency($agency)->create();

    $this->stops = collect([1, 2, 3])->map(
        fn (int $sequence): TourStop => TourStop::factory()
            ->forTour($this->tour)->atSequence($sequence)->create(),
    );

    $this->leg = fn (int $index, array $attributes = []): TourPeriod => TourPeriod::factory()->create([
        'tour_id' => $this->tour->id,
        'tour_stop_id' => $this->stops[$index]->id,
        'period_type' => 'driving',
        'sequence' => $index,
        ...$attributes,
    ]);

    $this->list = fn () => $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours?withStops=1')->assertOk();
});

it('rend chaque trajet avec l’arrêt vers lequel il mène', function (): void {
    ($this->leg)(1, ['distance_meters' => 12000, 'travel_minutes' => 15]);
    ($this->leg)(2, ['distance_meters' => 8400, 'travel_minutes' => 9]);

    expect(($this->list)()->json('data.0.legs'))->toEqual([
        ['tourStopId' => $this->stops[1]->id, 'distanceMeters' => 12000, 'travelMinutes' => 15],
        ['tourStopId' => $this->stops[2]->id, 'distanceMeters' => 8400, 'travelMinutes' => 9],
    ]);
});

/**
 * Une pause ou une attente ne sont pas un trajet. Les mêler ferait annoncer une
 * distance nulle entre deux arrêts éloignés.
 */
it('ignore les périodes qui ne sont pas de la conduite', function (): void {
    ($this->leg)(1, ['distance_meters' => 12000, 'travel_minutes' => 15]);
    ($this->leg)(2, ['period_type' => 'break', 'distance_meters' => 0, 'break_minutes' => 45]);

    expect(($this->list)()->json('data.0.legs'))->toHaveCount(1);
});

/** Sans les arrêts, la liste n'a pas l'usage des trajets : elle ne les charge pas. */
it('ne rend les trajets qu’avec les arrêts', function (): void {
    ($this->leg)(1, ['distance_meters' => 12000, 'travel_minutes' => 15]);

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours')->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('legs');
});

/**
 * Un itinéraire calculé avant que la durée par segment ne soit conservée n'a que
 * sa distance. Le rendre à zéro plutôt que de taire le trajet laisse l'écran
 * dire qu'il faut relancer le calcul.
 */
it('rend une durée nulle sur un itinéraire calculé avant', function (): void {
    ($this->leg)(1, ['distance_meters' => 12000]);

    expect(($this->list)()->json('data.0.legs.0.travelMinutes'))->toBe(0);
});
