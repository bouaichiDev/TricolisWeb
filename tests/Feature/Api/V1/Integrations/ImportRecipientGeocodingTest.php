<?php

use App\Modules\Integrations\Actions\LocateImportedAddresses;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Planning\Services\BatchGeocoder;
use App\Modules\Planning\Services\GeocodingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecipientImport;

/**
 * Le client final est **situé pendant l'import**, pas quand un worker passera.
 *
 * La file est simulée : si les coordonnées arrivent quand même, c'est bien
 * l'import qui les a demandées au service GPS, et non le Job de la commande.
 */
beforeEach(function (): void {
    RecipientImport::prepare($this);
    Queue::fake();

    OrganizationApiConfiguration::create([
        'organization_id' => $this->organization->id,
        'code' => GeocodingService::CONFIGURATION_CODE,
        'name' => 'Géocodage',
        'base_url' => 'https://gps.example.test',
        'auth_type' => 'none',
        'settings' => ['path' => '/TRC_GPS_API_V2/api/values/getLocation', 'queryKey' => 'adress'],
        'timeout_seconds' => 10,
        'is_active' => true,
    ]);
});

it('écrit la latitude et la longitude dans l’adresse du client final', function (): void {
    Http::fake(['*' => Http::response('<Result><Lat>46.2044</Lat><Lng>6.1432</Lng></Result>')]);

    $response = RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

    $address = RecipientImport::service('CMD-1')->address->fresh();

    expect((float) $address->latitude)->toBe(46.2044)
        ->and((float) $address->longitude)->toBe(6.1432)
        ->and($response->json('data.geocoding'))->toBe(['located' => 1, 'unlocated' => 0, 'pending' => 0]);
});

it('envoie au service l’adresse complète, pays compris', function (): void {
    Http::fake(['*' => Http::response('<Result><Lat>46.2044</Lat><Lng>6.1432</Lng></Result>')]);

    RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

    Http::assertSent(fn ($request) => $request['adress'] === '12 rue du Rhône, 1204 Genève, CH');
});

/** Une adresse sans point reste livrable : le service muet n'y est pour rien. */
it('crée la commande même quand le service GPS ne répond pas', function (): void {
    Http::fake(['*' => Http::response('', 500)]);

    $response = RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

    expect(RecipientImport::service('CMD-1')->address->latitude)->toBeNull()
        ->and($response->json('data.geocoding'))->toBe(['located' => 0, 'unlocated' => 1, 'pending' => 0]);
});

it('n’appelle pas le service pour un fichier refusé', function (): void {
    Http::fake();

    RecipientImport::send($this, RecipientImport::csv(['DEST_TEL' => '']))->assertStatus(422);

    Http::assertNothingSent();
});

/**
 * Les commandes sont écrites avant le géocodage : une requête coupée par PHP
 * ferait croire à un échec, et réimporter créerait des doublons. Budget épuisé,
 * la réponse part quand même — le reste attend en file.
 */
it('répond à temps et laisse en file ce qui dépasse le budget', function (): void {
    Http::fake();
    app()->instance(LocateImportedAddresses::class, new LocateImportedAddresses(app(BatchGeocoder::class), 0.0));

    $response = RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

    expect($response->json('data.geocoding'))->toBe(['located' => 0, 'unlocated' => 0, 'pending' => 1]);
    Http::assertNothingSent();
});
