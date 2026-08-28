<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Planning\Services\RouteGeometryService;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Le tracé routier d'une tournée.
 *
 * Le service GPS du projet ne rend aucune polyligne — vérifié le 28 août 2026,
 * ni `format=geojson`, ni `geometry=true`, ni `getRoute`. Le §101 autorise un
 * second fournisseur pour dessiner ; le §117 interdit d'en faire une table. Le
 * tracé se recalcule donc et ne vit qu'en cache.
 */
beforeEach(function (): void {
    $this->seed();
    Cache::flush();

    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->tour = Tour::factory()->forAgency($this->agency)->create(['status' => 'draft']);

    $this->configure = fn () => OrganizationApiConfiguration::create([
        'organization_id' => $this->organization->id,
        'code' => RouteGeometryService::CONFIGURATION_CODE,
        'name' => 'Tracé',
        'base_url' => 'https://osrm.example.test',
        'auth_type' => 'none',
        'settings' => [
            'path' => '/route/v1/driving/{coordinates}',
            'query' => ['overview' => 'full', 'geometries' => 'geojson'],
        ],
        'timeout_seconds' => 20,
        'is_active' => true,
    ]);

    $this->stopAt = function (int $sequence, ?float $latitude, ?float $longitude): TourStop {
        return TourStop::factory()->create([
            'tour_id' => $this->tour->id,
            'address_id' => Address::factory()->create([
                'latitude' => $latitude,
                'longitude' => $longitude,
            ])->id,
            'sequence' => $sequence,
            'status' => 'pending',
        ]);
    };

    $this->fetch = fn () => $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/tours/{$this->tour->id}/route-geometry");
});

it('rend le tracé en latitude, longitude', function (): void {
    ($this->configure)();
    ($this->stopAt)(1, 46.2333, 6.0833);
    ($this->stopAt)(2, 46.2044, 6.1432);

    // OSRM rend `[longitude, latitude]` : l'ordre inverse de tout le projet.
    Http::fake(['*' => Http::response([
        'routes' => [['geometry' => ['coordinates' => [[6.0833, 46.2333], [6.1432, 46.2044]]]]],
    ])]);

    $points = ($this->fetch)()->assertOk()->json('data.points');

    expect($points)->toBe([[46.2333, 6.0833], [46.2044, 6.1432]]);
});

/** Sans fournisseur déclaré, rien n'est appelé et rien n'est promis. */
it('rend une liste vide sans fournisseur', function (): void {
    ($this->stopAt)(1, 46.2333, 6.0833);
    ($this->stopAt)(2, 46.2044, 6.1432);

    Http::fake();

    expect(($this->fetch)()->assertOk()->json('data.points'))->toBe([]);

    Http::assertNothingSent();
});

it('n’appelle rien avec moins de deux points', function (): void {
    ($this->configure)();
    ($this->stopAt)(1, 46.2333, 6.0833);
    ($this->stopAt)(2, null, null);

    Http::fake();

    expect(($this->fetch)()->assertOk()->json('data.points'))->toBe([]);

    Http::assertNothingSent();
});

/** Une réponse illisible ne doit pas produire un tracé à moitié juste. */
it('rend une liste vide quand la réponse est illégale', function (): void {
    ($this->configure)();
    ($this->stopAt)(1, 46.2333, 6.0833);
    ($this->stopAt)(2, 46.2044, 6.1432);

    Http::fake(['*' => Http::response([
        'routes' => [['geometry' => ['coordinates' => [[6.0833, 46.2333], ['x', 'y']]]]],
    ])]);

    expect(($this->fetch)()->assertOk()->json('data.points'))->toBe([]);
});

/**
 * Le tracé se garde en cache sous une clé déduite des points : deux lectures ne
 * font qu'un appel distant.
 */
it('ne demande le tracé qu’une fois', function (): void {
    ($this->configure)();
    ($this->stopAt)(1, 46.2333, 6.0833);
    ($this->stopAt)(2, 46.2044, 6.1432);

    Http::fake(['*' => Http::response([
        'routes' => [['geometry' => ['coordinates' => [[6.0833, 46.2333], [6.1432, 46.2044]]]]],
    ])]);

    ($this->fetch)()->assertOk();
    ($this->fetch)()->assertOk();

    Http::assertSentCount(1);
});
