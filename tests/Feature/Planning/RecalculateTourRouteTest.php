<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Planning\Actions\RecalculateTourRoute;
use App\Modules\Planning\Services\GeocodingService;
use App\Modules\Planning\Services\RoutingService;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Support\Facades\Http;

/**
 * L'itinéraire d'une tournée, rangé là où le modèle le prévoyait.
 *
 * Aucune table n'est créée : un trajet entre deux arrêts est une période
 * `driving` attachée à l'arrêt d'arrivée, et `RecalculateTourTotals` en fait
 * déjà la somme dans `tours.distance_meters`.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->tour = Tour::factory()->forAgency($this->agency)->create(['status' => 'draft']);
    $this->action = app(RecalculateTourRoute::class);

    OrganizationApiConfiguration::create([
        'organization_id' => $this->organization->id,
        'code' => RoutingService::CONFIGURATION_CODE,
        'name' => 'Itinéraires',
        'base_url' => 'https://gps.example.test',
        'auth_type' => 'none',
        'settings' => ['path' => '/api/values/calculateRoute', 'profile' => 'truckfast'],
        'timeout_seconds' => 15,
        'is_active' => true,
    ]);

    $this->configureGeocoding = fn () => OrganizationApiConfiguration::create([
        'organization_id' => $this->organization->id,
        'code' => GeocodingService::CONFIGURATION_CODE,
        'name' => 'Géocodage',
        'base_url' => 'https://gps.example.test',
        'auth_type' => 'none',
        'settings' => ['path' => '/api/values/getLocation', 'queryKey' => 'adress'],
        'timeout_seconds' => 15,
        'is_active' => true,
    ]);

    $this->stopAt = function (int $sequence, ?float $latitude, ?float $longitude): TourStop {
        $address = Address::factory()->create(['latitude' => $latitude, 'longitude' => $longitude]);

        return TourStop::factory()->create([
            'tour_id' => $this->tour->id,
            'address_id' => $address->id,
            'sequence' => $sequence,
            'status' => 'pending',
        ]);
    };

    $this->answer = fn (int $meters, int $seconds) => Http::fake([
        '*' => Http::response(
            "<Result><Distance>{$meters}</Distance><TravelTime>{$seconds}</TravelTime></Result>",
        ),
    ]);
});

it('range chaque segment dans une période de conduite', function (): void {
    ($this->stopAt)(1, 33.59, -7.62);
    ($this->stopAt)(2, 33.55, -7.60);
    ($this->stopAt)(3, 33.97, -6.85);
    ($this->answer)(12000, 900);

    expect($this->action->execute($this->tour))->toBe(2);

    $periods = $this->tour->periods()->where('period_type', 'driving')->get();

    // Un segment de moins que d'arrets : le premier n'a pas de trajet entrant.
    expect($periods)->toHaveCount(2)
        ->and($periods->sum('distance_meters'))->toBe(24000);
});

/** §92 : la distance de la tournée est la somme de ses périodes, déjà câblée. */
it('alimente la distance et le temps de conduite de la tournée', function (): void {
    ($this->stopAt)(1, 33.59, -7.62);
    ($this->stopAt)(2, 33.55, -7.60);
    ($this->answer)(12000, 900);

    $this->action->execute($this->tour);

    $tour = $this->tour->fresh();

    expect($tour->distance_meters)->toBe(12000)
        ->and($tour->driving_time_minutes)->toBe(15);
});

/**
 * §83 : géocoder « uniquement les adresses nécessaires à l'opération ».
 *
 * Une commande planifiée sur une adresse jamais située doit obtenir son point
 * **maintenant** : c'est ici qu'on sait laquelle l'opération réclame. Abandonner
 * laisserait la tournée sans tracé pour toujours.
 */
it('situe en chemin l’adresse qui n’a pas de point', function (): void {
    ($this->configureGeocoding)();

    ($this->stopAt)(1, 33.59, -7.62);
    $missing = ($this->stopAt)(2, null, null);

    Http::fake([
        '*getLocation*' => Http::response('<Result><Lat>33.55</Lat><Lng>-7.60</Lng></Result>'),
        '*calculateRoute*' => Http::response(
            '<Result><Distance>12000</Distance><TravelTime>900</TravelTime></Result>',
        ),
    ]);

    expect($this->action->execute($this->tour))->toBe(1);

    expect($missing->address->fresh()->latitude)->not->toBeNull();
    expect($this->tour->fresh()->distance_meters)->toBe(12000);
});

/**
 * Quand même le géocodage ne trouve rien, l'arrêt reste sans point : relier ses
 * voisins par-dessus inventerait une route qui ne passe pas par là.
 */
it('n’annonce rien quand une adresse reste introuvable', function (): void {
    ($this->configureGeocoding)();

    ($this->stopAt)(1, 33.59, -7.62);
    ($this->stopAt)(2, null, null);
    ($this->stopAt)(3, 33.97, -6.85);

    Http::fake([
        '*getLocation*' => Http::response('<Result><Lat>0</Lat><Lng>0</Lng></Result>'),
        '*calculateRoute*' => Http::response(
            '<Result><Distance>12000</Distance><TravelTime>900</TravelTime></Result>',
        ),
    ]);

    expect($this->action->execute($this->tour))->toBe(0)
        ->and($this->tour->fresh()->distance_meters)->toBe(0);

    // Le trajet n'a jamais ete demande : un point manquant arrete tout avant.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'calculateRoute'));
});

/** Sans configuration de géocodage, l'abandon reste la seule issue. */
it('abandonne quand rien ne peut situer l’adresse', function (): void {
    ($this->stopAt)(1, 33.59, -7.62);
    ($this->stopAt)(2, null, null);
    ($this->answer)(12000, 900);

    expect($this->action->execute($this->tour))->toBe(0);

    Http::assertNothingSent();
});

/** Mieux vaut « non calculé » qu'une tournée annoncée plus courte qu'elle n'est. */
it('efface l’itinéraire quand le service ne répond pas', function (): void {
    ($this->stopAt)(1, 33.59, -7.62);
    ($this->stopAt)(2, 33.55, -7.60);

    // Une sequence, et non deux `Http::fake` : le second n'ecrase pas le
    // premier, il s'y ajoute, et le motif initial continuerait de repondre.
    Http::fakeSequence()
        ->push('<Result><Distance>12000</Distance><TravelTime>900</TravelTime></Result>')
        ->pushStatus(503);

    $this->action->execute($this->tour);
    expect($this->tour->fresh()->distance_meters)->toBe(12000);

    expect($this->action->execute($this->tour))->toBe(0);

    $tour = $this->tour->fresh();

    expect($tour->distance_meters)->toBe(0)
        ->and($tour->driving_time_minutes)->toBe(0)
        ->and($tour->periods()->where('period_type', 'driving')->count())->toBe(0);
});

/** Un recalcul ne doit pas empiler les segments du précédent. */
it('remplace l’itinéraire précédent au lieu de s’y ajouter', function (): void {
    ($this->stopAt)(1, 33.59, -7.62);
    ($this->stopAt)(2, 33.55, -7.60);
    ($this->answer)(12000, 900);

    $this->action->execute($this->tour);
    $this->action->execute($this->tour);

    expect($this->tour->periods()->where('period_type', 'driving')->count())->toBe(1)
        ->and($this->tour->fresh()->distance_meters)->toBe(12000);
});
