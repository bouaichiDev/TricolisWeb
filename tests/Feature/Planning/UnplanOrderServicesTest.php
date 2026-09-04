<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;

/**
 * Retirer des services d'une tournée.
 *
 * Symétrique de la planification, avec une nuance que le §115 laisse au
 * serveur : dans un brouillon rien ne s'est passé sur le terrain et
 * l'affectation s'efface ; une fois la tournée confirmée, elle se désactive
 * pour garder trace du passage.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->tour = Tour::factory()->forAgency($this->agency)->create(['status' => 'draft']);

    $this->orderAt = function (Address $address, int $services = 1): Order {
        $order = Order::factory()->forOrganization($this->organization)->create();

        OrderService::factory($services)->create([
            'order_id' => $order->id,
            'address_id' => $address->id,
            'status' => 'ready_to_plan',
        ]);

        return $order;
    };

    $this->plan = fn (array $payload) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$this->tour->id}/plan", $payload);

    $this->unplan = fn (array $payload) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$this->tour->id}/unplan", $payload);
});

it('rend au pool les services retirés', function (): void {
    $order = ($this->orderAt)(Address::factory()->create(), 2);

    ($this->plan)(['orderIds' => [$order->id]])->assertOk();

    $response = ($this->unplan)(['orderIds' => [$order->id]])->assertOk();

    expect($response->json('data.unplanned'))->toHaveCount(2)
        ->and($response->json('data.rejected'))->toBe([]);

    // Le pool les revoit : c'est la preuve du retour, pas le compteur.
    $pool = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/planning/pool')->assertOk();

    expect($pool->json('data.*.id'))->toContain($order->id);
});

/** §115 : dans un brouillon, aucun historique n’est nécessaire. */
it('efface l’affectation dans un brouillon', function (): void {
    $order = ($this->orderAt)(Address::factory()->create());

    ($this->plan)(['orderIds' => [$order->id]])->assertOk();
    ($this->unplan)(['orderIds' => [$order->id]])->assertOk();

    $serviceId = OrderService::where('order_id', $order->id)->value('id');

    expect(TourStopService::where('order_service_id', $serviceId)->exists())->toBeFalse();
});

/**
 * §31 : une fois la tournée confirmée, l’affectation est la mémoire du
 * parcours du service. Elle se désactive, elle ne s’efface pas.
 */
it('désactive l’affectation d’une tournée confirmée', function (): void {
    $order = ($this->orderAt)(Address::factory()->create());

    ($this->plan)(['orderIds' => [$order->id]])->assertOk();

    $this->tour->forceFill(['status' => 'confirmed'])->save();

    ($this->unplan)(['orderIds' => [$order->id]])->assertOk();

    $serviceId = OrderService::where('order_id', $order->id)->value('id');
    $assignment = TourStopService::where('order_service_id', $serviceId)->first();

    expect($assignment)->not->toBeNull()
        ->and($assignment->is_active_assignment)->toBeFalse();
});

it('retire l’arrêt devenu vide et resserre les rangs', function (): void {
    $first = ($this->orderAt)(Address::factory()->create());
    $second = ($this->orderAt)(Address::factory()->create());

    ($this->plan)(['orderIds' => [$first->id, $second->id]])->assertOk();

    expect(TourStop::where('tour_id', $this->tour->id)->count())->toBe(2);

    ($this->unplan)(['orderIds' => [$first->id]])->assertOk();

    $stops = TourStop::where('tour_id', $this->tour->id)->orderBy('sequence')->get();

    expect($stops)->toHaveCount(1)
        ->and($stops->first()->sequence)->toBe(1);
});

/**
 * Retirer un service retire ses frères, dans cette tournée.
 *
 * Règle posée le 27 août 2026, symétrique du §40 : glisser une commande prend
 * tous ses services éligibles, la retirer les rend tous. Sans cela, retirer la
 * livraison laisserait le chargement au dépôt — un arrêt où le camion charge ce
 * que personne n'ira livrer.
 */
it('retire toute la commande quand on retire un seul de ses services', function (): void {
    $address = Address::factory()->create();
    $order = ($this->orderAt)($address, 2);

    ($this->plan)(['orderIds' => [$order->id]])->assertOk();

    $one = OrderService::where('order_id', $order->id)->orderBy('sequence')->value('id');

    $response = ($this->unplan)(['orderServiceIds' => [$one]])->assertOk();

    expect($response->json('data.unplanned'))->toHaveCount(2)
        ->and(TourStop::where('tour_id', $this->tour->id)->count())->toBe(0);
});

/** L'extension s'arrête à cette tournée : ce qui est ailleurs y reste. */
it('n’emporte pas les services de la même commande placés ailleurs', function (): void {
    $order = ($this->orderAt)(Address::factory()->create(), 2);
    $services = OrderService::where('order_id', $order->id)->orderBy('sequence')->pluck('id');

    $other = Tour::factory()->forAgency($this->agency)->create(['status' => 'draft']);

    ($this->plan)(['orderServiceIds' => [$services[0]]])->assertOk();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$other->id}/plan", ['orderServiceIds' => [$services[1]]])
        ->assertOk();

    ($this->unplan)(['orderServiceIds' => [$services[0]]])->assertOk();

    // Le second reste planifie dans l'autre tournee.
    expect(TourStopService::where('order_service_id', $services[1])
        ->where('is_active_assignment', true)->exists())->toBeTrue();
});

/** Deux commandes à la même adresse : retirer l'une garde l'arrêt pour l'autre. */
it('garde l’arrêt quand une autre commande l’occupe encore', function (): void {
    $address = Address::factory()->create();
    $kept = ($this->orderAt)($address);
    $removed = ($this->orderAt)($address);

    ($this->plan)(['orderIds' => [$kept->id, $removed->id]])->assertOk();

    $one = OrderService::where('order_id', $removed->id)->value('id');

    ($this->unplan)(['orderServiceIds' => [$one]])->assertOk();

    expect(TourStop::where('tour_id', $this->tour->id)->count())->toBe(1);
});

/** Ce qui a été livré ne retourne pas dans le pool. */
it('refuse de déplanifier une tournée terminée', function (): void {
    $order = ($this->orderAt)(Address::factory()->create());

    ($this->plan)(['orderIds' => [$order->id]])->assertOk();

    $this->tour->forceFill(['status' => 'completed'])->save();

    ($this->unplan)(['orderIds' => [$order->id]])->assertStatus(422);
});

/** Un service que cette tournée ne porte pas est nommé, pas ignoré. */
it('nomme le service qui n’est pas planifié ici', function (): void {
    $order = ($this->orderAt)(Address::factory()->create());
    $serviceId = OrderService::where('order_id', $order->id)->value('id');

    $response = ($this->unplan)(['orderServiceIds' => [$serviceId]])->assertOk();

    expect($response->json('data.unplanned'))->toBe([])
        ->and($response->json('data.rejected.0.reason'))->toBe('not_planned');
});

it('recalcule les totaux et journalise le retrait', function (): void {
    $order = ($this->orderAt)(Address::factory()->create());

    ($this->plan)(['orderIds' => [$order->id]])->assertOk();
    ($this->unplan)(['orderIds' => [$order->id]])->assertOk();

    expect($this->tour->fresh()->total_customers)->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'tour.services_unplanned',
        'entity_id' => $this->tour->id,
    ]);
});

/**
 * L'arrêt dit de quelles commandes il vient.
 *
 * Sans cela, la carte et les colonnes montrent une adresse et un compteur, mais
 * rien qui permette de remonter à ce que le camion vient y faire.
 */
it('nomme les commandes posées sur l’arrêt, sans doublon', function (): void {
    $address = Address::factory()->create();
    $order = ($this->orderAt)($address, 2);

    ($this->plan)(['orderIds' => [$order->id]])->assertOk();

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours?withStops=1')->assertOk();

    $orders = $response->json('data.0.stops.0.orders');

    // Deux services d'une meme commande ne font qu'une ligne : c'est la
    // commande qu'on ouvre, pas chacun de ses services.
    // Le temps sur place est la somme des services de l'arret : deux services
    // de trente minutes en font soixante, et c'est cela qu'on deplie pour lire.
    expect($orders)->toHaveCount(1)
        ->and($orders[0]['id'])->toBe($order->id)
        ->and($orders[0]['services'])->toHaveCount(2)
        ->and($orders[0]['serviceMinutes'])->toBe(60)
        ->and($orders[0]['customerName'])->not->toBeNull();
});

/** Une affectation retirée ne doit plus renvoyer vers sa commande. */
it('oublie la commande d’un service retiré', function (): void {
    $address = Address::factory()->create();
    $kept = ($this->orderAt)($address);
    $removed = ($this->orderAt)($address);

    ($this->plan)(['orderIds' => [$kept->id, $removed->id]])->assertOk();
    ($this->unplan)(['orderIds' => [$removed->id]])->assertOk();

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours?withStops=1')->assertOk();

    expect($response->json('data.0.stops.0.orders.*.id'))->toBe([$kept->id]);
});
