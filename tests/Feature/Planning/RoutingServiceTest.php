<?php

use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Planning\Services\RoutingService;
use Illuminate\Support\Facades\Http;

/**
 * Distances et durées entre les points d'une tournée.
 *
 * Le calcul se fait **par paires** : le service ne rend qu'un total, alors que
 * l'écran doit afficher la distance entre chaque arrêt. Les paires donnent les
 * deux ; un appel global ne donnerait que le second.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();

    $this->configure = fn (): OrganizationApiConfiguration => OrganizationApiConfiguration::create([
        'organization_id' => $this->organization->id,
        'code' => RoutingService::CONFIGURATION_CODE,
        'name' => 'Itinéraires',
        'base_url' => 'https://gps.example.test',
        'auth_type' => 'none',
        'settings' => [
            'path' => '/TRC_GPS_API_V2/api/values/calculateRoute',
            'profile' => 'truckfast',
        ],
        'timeout_seconds' => 15,
        'is_active' => true,
    ]);

    $this->service = app(RoutingService::class);
    $this->paris = [48.8566, 2.3522];
    $this->lyon = [45.7640, 4.8357];
});

/**
 * Les unités, figées sur la réponse de référence.
 *
 * Paris → Lyon : 465 536 et 23 611. Ce sont 465 km en 6 h 33 — des **mètres**
 * et des **secondes**. Aucune autre lecture n'est cohérente : en kilomètres le
 * trajet ferait le tour de la Terre onze fois, en minutes il durerait seize
 * jours. Ce test empêche d'écrire des kilomètres dans `distance_meters`.
 */
it('reads meters and seconds', function (): void {
    $leg = ($this->service)->parse(
        '<Result><Distance>465536</Distance><TrafficTime>23611</TrafficTime>'
        .'<BaseTime>23611</BaseTime><TravelTime>23611</TravelTime></Result>',
    );

    expect($leg)->not->toBeNull();
    expect($leg->distanceMeters)->toBe(465536);
    expect($leg->travelSeconds)->toBe(23611);
    expect($leg->trafficSeconds)->toBe(23611);

    // 23 611 s = 393,5 min, arrondies au superieur : une tournee annoncee plus
    // courte qu'elle ne l'est fait rater des rendez-vous.
    expect($leg->travelMinutes())->toBe(394);
});

it('builds the waypoints the service expects', function (): void {
    expect(($this->service)->waypoints($this->paris, $this->lyon))
        ->toBe('wy48.8566~2.3522*wy45.764~4.8357');
});

it('returns one leg less than there are points', function (): void {
    ($this->configure)();

    Http::fake(['*' => Http::response(
        '<Result><Distance>12400</Distance><TravelTime>1080</TravelTime></Result>',
    )]);

    $legs = ($this->service)->legs([$this->paris, $this->lyon, [43.2965, 5.3698]], $this->organization->id);

    expect($legs)->toHaveCount(2);
    expect($legs[0]->distanceMeters)->toBe(12400);
    expect($legs[0]->travelMinutes())->toBe(18);

    // Le profil vient de la configuration, jamais du code appelant.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'profile=truckfast'));
});

it('needs at least two points', function (): void {
    ($this->configure)();
    Http::fake();

    expect(($this->service)->legs([$this->paris], $this->organization->id))->toBe([]);
    Http::assertNothingSent();
});

/**
 * Un segment manquant rendrait le total faux sans le dire : mieux vaut ne rien
 * annoncer que d'annoncer trop court.
 */
it('gives nothing when a leg fails', function (): void {
    ($this->configure)();

    Http::fakeSequence()
        ->push('<Result><Distance>12400</Distance><TravelTime>1080</TravelTime></Result>')
        ->push('', 500);

    $legs = ($this->service)->legs([$this->paris, $this->lyon, [43.2965, 5.3698]], $this->organization->id);

    expect($legs)->toBe([]);
});

it('gives nothing without a configuration', function (): void {
    Http::fake();

    expect(($this->service)->legs([$this->paris, $this->lyon], $this->organization->id))->toBe([]);
    Http::assertNothingSent();
});

it('rejects an unreadable answer', function (): void {
    expect(($this->service)->parse('pas du xml'))->toBeNull();
    expect(($this->service)->parse('<Result><Distance>abc</Distance><TravelTime>10</TravelTime></Result>'))
        ->toBeNull();
});
