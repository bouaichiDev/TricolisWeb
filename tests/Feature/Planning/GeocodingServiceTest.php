<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Planning\Services\GeocodingService;
use Illuminate\Support\Facades\Http;

/**
 * Le géocodage donne ses coordonnées à une adresse qui n'en a pas.
 *
 * L'appel se décrit dans `organization_api_configurations` : le projet n'a pas
 * de table `configs`, et en créer une donnerait deux référentiels d'API
 * externes à tenir.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();

    $this->configure = fn (array $settings = []): OrganizationApiConfiguration => OrganizationApiConfiguration::create([
        'organization_id' => $this->organization->id,
        'code' => GeocodingService::CONFIGURATION_CODE,
        'name' => 'Géocodage',
        'base_url' => 'https://gps.example.test',
        'auth_type' => 'none',
        'settings' => array_merge([
            'path' => '/TRC_GPS_API_V2/api/values/getLocation',
            'queryKey' => 'adress',
        ], $settings),
        'timeout_seconds' => 10,
        'is_active' => true,
    ]);

    $this->service = app(GeocodingService::class);
});

it('completes an address and keeps the same row', function (): void {
    ($this->configure)();

    Http::fake(['*' => Http::response('<Result><Lat>48.857170093</Lat><Lng>2.3413999257</Lng></Result>')]);

    $address = Address::factory()->create([
        'latitude' => null, 'longitude' => null,
        'address_line_1' => '12 rue des Oliviers', 'postal_code' => '20000', 'city' => 'Casablanca',
    ]);

    expect(($this->service)->locate($address, $this->organization->id))->toBeTrue();

    // La meme ligne est mise a jour : une seconde adresse pour les memes murs
    // ferait diverger ce que deux ecrans affichent.
    expect(Address::where('address_line_1', '12 rue des Oliviers')->count())->toBe(1);
    // Huit decimales en base — `decimal(10,8)` — contre neuf rendues par le
    // service. L'ecart vaut un millimetre : la colonne tranche, et c'est bien
    // assez pour situer un camion.
    expect((float) $address->fresh()->latitude)->toBe(48.85717009);
    expect((float) $address->fresh()->longitude)->toBe(2.34139993);

    // L'orthographe `adress` est celle du service : la corriger ferait echouer.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'adress=')
        && str_contains($request->url(), '/getLocation'));
});

/** Des murs ne se déplacent pas : une adresse déjà située n'est pas redemandée. */
it('does not call the service for an address that already has coordinates', function (): void {
    ($this->configure)();
    Http::fake();

    $address = Address::factory()->create(['latitude' => 48.85, 'longitude' => 2.34]);

    expect(($this->service)->locate($address, $this->organization->id))->toBeTrue();

    Http::assertNothingSent();
});

it('refuses zero coordinates', function (): void {
    ($this->configure)();

    // Le service rend 0,0 pour une adresse introuvable. Ce point existe, au
    // large du Ghana : l'accepter afficherait un client en plein ocean.
    Http::fake(['*' => Http::response('<Result><Lat>0</Lat><Lng>0</Lng></Result>')]);

    $address = Address::factory()->create(['latitude' => null, 'longitude' => null]);

    expect(($this->service)->locate($address, $this->organization->id))->toBeFalse();
    expect($address->fresh()->latitude)->toBeNull();
});

it('survives an unreadable answer, an error and an unreachable service', function (): void {
    ($this->configure)();
    $address = Address::factory()->create(['latitude' => null, 'longitude' => null]);

    Http::fake(['*' => Http::response('pas du xml')]);
    expect(($this->service)->locate($address, $this->organization->id))->toBeFalse();

    Http::fake(['*' => Http::response('<Result><Lat>abc</Lat><Lng>2.3</Lng></Result>')]);
    expect(($this->service)->locate($address, $this->organization->id))->toBeFalse();

    Http::fake(['*' => Http::response('', 500)]);
    expect(($this->service)->locate($address, $this->organization->id))->toBeFalse();

    expect($address->fresh()->latitude)->toBeNull();
});

it('does nothing without a configuration', function (): void {
    Http::fake();

    $address = Address::factory()->create(['latitude' => null, 'longitude' => null]);

    expect(($this->service)->locate($address, $this->organization->id))->toBeFalse();
    Http::assertNothingSent();
});

/**
 * `name` n'entre pas dans la chaîne : « Entrepôt nord » ne situe rien, et
 * l'envoyer ferait dériver le résultat vers un homonyme.
 */
it('builds the address line from the located fields only', function (): void {
    $address = Address::factory()->make([
        'name' => 'Entrepôt nord',
        'address_number' => '12', 'route' => 'rue des Oliviers',
        'address_line_1' => null, 'address_line_2' => null,
        'postal_code' => '20000', 'city' => 'Casablanca', 'country' => 'MA',
    ]);

    $line = ($this->service)->describe($address);

    expect($line)->toBe('12 rue des Oliviers, 20000 Casablanca, MA');
    expect($line)->not->toContain('Entrepôt');
});
