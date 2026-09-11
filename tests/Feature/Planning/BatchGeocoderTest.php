<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Planning\Services\BatchGeocoder;
use App\Modules\Planning\Services\GeocodingService;
use Illuminate\Support\Facades\Http;

/**
 * Situer plusieurs adresses pendant une requête, sans jamais la faire tomber.
 *
 * Trente appels l'un après l'autre dépassaient les 30 s de PHP, **après**
 * l'écriture des commandes d'un import : l'écran annonçait un échec pour un
 * import réussi.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();

    $this->configure = fn (): OrganizationApiConfiguration => OrganizationApiConfiguration::create([
        'organization_id' => $this->organization->id,
        'code' => GeocodingService::CONFIGURATION_CODE,
        'name' => 'Géocodage',
        'base_url' => 'https://gps.example.test',
        'auth_type' => 'none',
        'settings' => ['path' => '/getLocation', 'queryKey' => 'adress'],
        'timeout_seconds' => 10,
        'is_active' => true,
    ]);

    $this->addresses = fn (int $count) => Address::factory()->count($count)->create([
        'address_line_1' => 'Rue du Rhône 48', 'postal_code' => '1204', 'city' => 'Genève', 'country' => 'CH',
        'latitude' => null, 'longitude' => null,
    ]);

    $this->geocoder = app(BatchGeocoder::class);
});

it('situe toutes les adresses d’un lot plus grand qu’un paquet', function (): void {
    ($this->configure)();
    Http::fake(['*' => Http::response('<Result><Lat>46.2044</Lat><Lng>6.1432</Lng></Result>')]);

    $result = $this->geocoder->locate(($this->addresses)(20), $this->organization->id, 10.0);

    expect($result)->toBe(['located' => 20, 'unlocated' => 0, 'pending' => 0])
        ->and(Address::whereNull('latitude')->whereIn('city', ['Genève'])->count())->toBe(0);
    Http::assertSentCount(20);
});

it('ne redemande pas une adresse déjà située', function (): void {
    ($this->configure)();
    Http::fake();

    $address = Address::factory()->create(['latitude' => 46.2, 'longitude' => 6.1]);

    expect($this->geocoder->locate([$address], $this->organization->id, 10.0))
        ->toBe(['located' => 1, 'unlocated' => 0, 'pending' => 0]);
    Http::assertNothingSent();
});

it('laisse sans point une adresse que le service ne trouve pas ou ne joint pas', function (): void {
    ($this->configure)();
    [$lost, $unreachable] = ($this->addresses)(2)->all();
    $unreachable->update(['address_line_1' => 'Rue injoignable 1']);

    // 0,0 est la réponse du service pour une adresse qu'il ne trouve pas.
    Http::fake(fn ($request) => str_contains(urldecode((string) $request->url()), 'injoignable')
        ? Http::failedConnection()
        : Http::response('<Result><Lat>0</Lat><Lng>0</Lng></Result>'));

    $result = $this->geocoder->locate([$lost, $unreachable->fresh()], $this->organization->id, 10.0);

    expect($result)->toBe(['located' => 0, 'unlocated' => 2, 'pending' => 0])
        ->and($lost->fresh()->latitude)->toBeNull()
        ->and($unreachable->fresh()->latitude)->toBeNull();
});

it('ne commence pas ce qui ne tient plus dans le budget', function (): void {
    ($this->configure)();
    Http::fake();

    expect($this->geocoder->locate(($this->addresses)(3), $this->organization->id, 0.0))
        ->toBe(['located' => 0, 'unlocated' => 0, 'pending' => 3]);
    Http::assertNothingSent();
});

it('n’appelle rien sans configuration de géocodage', function (): void {
    Http::fake();

    expect($this->geocoder->locate(($this->addresses)(2), $this->organization->id, 10.0))
        ->toBe(['located' => 0, 'unlocated' => 2, 'pending' => 0]);
    Http::assertNothingSent();
});
