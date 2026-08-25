<?php

use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Les positions du vehicule qui execute une commande.
 *
 * Le jeton du fournisseur ne quitte jamais le serveur : c'est toute la raison
 * d'etre de cette route. Un jeton pose dans du JavaScript est lisible par
 * quiconque ouvre les outils de developpement, et il donne acces a l'historique
 * de tous les vehicules.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->order = Order::factory()->create(['organization_id' => $this->organization->id]);

    $this->configure = function (): OrganizationApiConfiguration {
        $configuration = new OrganizationApiConfiguration([
            'organization_id' => $this->organization->id,
            'code' => 'driver_position',
            'name' => 'Flespi',
            'base_url' => 'https://flespi.io',
            'auth_type' => 'api_key',
            'timeout_seconds' => 5,
            'is_active' => true,
        ]);
        $configuration->setCredentials('jeton-tres-secret');
        $configuration->save();

        return $configuration;
    };

    $this->positions = fn () => $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$this->order->id}/positions");
});

/** Rien de configure : la commande reste consultable, seule la carte manque. */
it('answers with an empty list when no telematics is configured', function (): void {
    ($this->positions)()
        ->assertOk()
        ->assertJsonPath('data.points', [])
        ->assertJsonPath('data.reason', 'not_configured');
});

/** Une tournee sans reference n'est pas suivie : c'est le cas normal. */
it('answers with an empty list when no tour carries a reference', function (): void {
    ($this->configure)();

    ($this->positions)()
        ->assertOk()
        ->assertJsonPath('data.reason', 'no_reference');
});

/**
 * Le secret ne traverse jamais la reponse.
 *
 * Il part vers le fournisseur, dans l'en-tete que Flespi attend, et nulle part
 * ailleurs.
 */
it('sends the secret to the provider and never to the client', function (): void {
    $configuration = ($this->configure)();

    Http::fake([
        'flespi.io/*' => Http::response([
            'result' => [
                ['position.latitude' => 33.5731, 'position.longitude' => -7.5898, 'timestamp' => 1786000000],
                ['position.latitude' => 33.58, 'position.longitude' => -7.6, 'timestamp' => 1786000060],
                // Sans coordonnees : ignoree plutot qu'interpretee.
                ['timestamp' => 1786000120],
            ],
        ]),
    ]);

    // Une tournee de la commande porte la reference.
    $tour = Tour::factory()->create([
        'organization_id' => $this->organization->id,
        'telematics_reference' => 'PLAN-42',
    ]);
    $stop = TourStop::factory()->create(['tour_id' => $tour->id]);
    $service = OrderService::factory()->create(['order_id' => $this->order->id]);
    DB::table('tour_stop_services')->insert([
        'id' => (string) Str::ulid(),
        'tour_stop_id' => $stop->id,
        'order_service_id' => $service->id,
        'sequence_within_stop' => 1,
        'status' => 'pending',
    ]);

    $response = ($this->positions)()->assertOk();

    expect($response->json('data.points'))->toHaveCount(2)
        ->and($response->json('data.points.0.latitude'))->toBe(33.5731);

    // Le secret n'apparait nulle part dans la reponse.
    expect($response->getContent())->not->toContain('jeton-tres-secret');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'FlespiToken jeton-tres-secret'));

    expect($configuration->fresh()->last_used_at)->not->toBeNull();
});

/** Le fournisseur injoignable ne fait pas echouer l'ecran. */
it('degrades quietly when the provider fails', function (): void {
    ($this->configure)();
    Http::fake(['flespi.io/*' => Http::response([], 500)]);

    ($this->positions)()->assertOk()->assertJsonPath('data.points', []);
});
