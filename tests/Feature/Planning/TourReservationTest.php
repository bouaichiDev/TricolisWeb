<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\Tour;

/**
 * La réservation d'une tournée pendant sa composition.
 *
 * Elle se prend au premier geste et se rend quand on a fini. **Elle ne touche
 * pas au statut** : confirmer ses modifications sur la carte ne confirme pas la
 * tournée — décision du 28 août 2026, qui est aussi ce qui a rendu ces deux
 * colonnes nécessaires : la fin de l'exclusivité ne pouvait plus se déduire de
 * la sortie du brouillon.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->tour = Tour::factory()->forAgency($this->agency)->create(['status' => 'draft']);

    $this->order = function (): Order {
        $order = Order::factory()->forOrganization($this->organization)->create();

        OrderService::factory()->create([
            'order_id' => $order->id,
            'address_id' => Address::factory()->create()->id,
            'status' => 'ready_to_plan',
        ]);

        return $order;
    };

    $this->reserve = fn () => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$this->tour->id}/reserve");

    $this->plan = fn (array $payload) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$this->tour->id}/plan", $payload);
});

/**
 * La réservation est **demandée**, pas prise à chaque versement.
 *
 * C'est la carte qui réserve, parce que c'est elle qui cache son travail
 * jusqu'à confirmation. Un glisser-déposer depuis les colonnes agit tout de
 * suite : le réserver aurait caché son propre résultat.
 */
it('ne réserve pas la tournée sur un simple versement', function (): void {
    ($this->plan)(['orderIds' => [($this->order)()->id]])->assertOk();

    expect($this->tour->fresh()->locked_by)->toBeNull();
});

it('réserve la tournée quand on le demande', function (): void {
    ($this->reserve)()->assertNoContent();

    expect($this->tour->fresh()->locked_by)->toBe($this->user->id);
});

/** C'est tout l'objet de la décision : composer n'est pas confirmer. */
it('ne touche pas au statut en réservant', function (): void {
    ($this->reserve)()->assertNoContent();
    ($this->plan)(['orderIds' => [($this->order)()->id]])->assertOk();

    expect($this->tour->fresh()->status->value)->toBe('draft');
});

it('rend la tournée sans changer son statut', function (): void {
    ($this->reserve)()->assertNoContent();
    ($this->plan)(['orderIds' => [($this->order)()->id]])->assertOk();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$this->tour->id}/release")->assertNoContent();

    $tour = $this->tour->fresh();

    expect($tour->locked_by)->toBeNull()
        ->and($tour->locked_at)->toBeNull()
        ->and($tour->status->value)->toBe('draft')
        // Ce qui a ete pose reste pose : rendre la main n'efface rien.
        ->and($tour->stops()->count())->toBeGreaterThan(0);
});

/**
 * Un autre planificateur ne compose pas une tournée retenue.
 *
 * La protection est **backend**, comme le §25 l'exige : l'écran ne fait que ne
 * pas promettre ce que le serveur refuserait.
 */
it('refuse un autre planificateur, en le renseignant', function (): void {
    ($this->reserve)()->assertNoContent();

    $other = User::factory()->create(['first_name' => 'Sara', 'last_name' => 'Amrani']);

    $this->tour->forceFill(['locked_by' => $other->id, 'locked_at' => now()])->save();

    ($this->plan)(['orderIds' => [($this->order)()->id]])
        ->assertStatus(403)
        ->assertJsonPath('message', 'Tournée réservée par Sara Amrani. Demandez-lui de la libérer.');
});

it('nomme qui retient la tournée dans la liste', function (): void {
    ($this->reserve)()->assertNoContent();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours')->assertOk()
        ->assertJsonPath('data.0.lockedBy.id', $this->user->id);
});

/** Hors brouillon la réservation n'a plus de sens : elle ne se prend pas. */
it('ne réserve pas une tournée qui a quitté le brouillon', function (): void {
    $this->tour->forceFill(['status' => 'confirmed'])->save();

    ($this->reserve)()->assertNoContent();

    expect($this->tour->fresh()->locked_by)->toBeNull();
});

/**
 * Une composition en cours ne se voit pas ailleurs.
 *
 * Décision du 28 août 2026 : tant que le planificateur n'a pas confirmé, la vue
 * en colonnes doit montrer la tournée telle qu'elle était avant qu'il ne
 * commence. Sinon un collègue lit un plan à moitié fait et le prend pour acquis.
 */
it('cache aux colonnes ce qui n’est pas confirmé', function (): void {
    ($this->reserve)()->assertNoContent();
    ($this->plan)(['orderIds' => [($this->order)()->id]])->assertOk();

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours?withStops=1')->assertOk();

    expect($response->json('data.0.stops'))->toBe([])
        ->and($response->json('data.0.pendingChanges'))->toBe(1);
});

/** Les totaux aussi restent à leur dernière valeur confirmée. */
it('fige les totaux pendant la composition', function (): void {
    ($this->reserve)()->assertNoContent();
    ($this->plan)(['orderIds' => [($this->order)()->id]])->assertOk();

    expect($this->tour->fresh()->total_customers)->toBe(0);
});

it('montre tout une fois la tournée rendue', function (): void {
    ($this->reserve)()->assertNoContent();
    ($this->plan)(['orderIds' => [($this->order)()->id]])->assertOk();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$this->tour->id}/release")->assertNoContent();

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours?withStops=1')->assertOk();

    expect($response->json('data.0.stops'))->toHaveCount(1)
        ->and($response->json('data.0.pendingChanges'))->toBe(0)
        // Les totaux sont repris au moment où la tournée est rendue.
        ->and($this->tour->fresh()->total_customers)->toBe(1);
});

/**
 * La carte, elle, doit voir ce qu'elle compose.
 *
 * Cacher le travail aux colonnes est une chose ; le cacher à celui qui le fait
 * en serait une autre — il ne saurait plus ce qu'il vient de poser.
 */
it('montre la composition à celui qui la mène', function (): void {
    ($this->reserve)()->assertNoContent();
    ($this->plan)(['orderIds' => [($this->order)()->id]])->assertOk();

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours?withStops=1&includePending=1')->assertOk();

    expect($response->json('data.0.stops'))->toHaveCount(1);
});

/** Mais à lui seul : le montrer aux autres reviendrait à n'avoir rien caché. */
it('refuse la composition d’un autre, même demandée', function (): void {
    ($this->reserve)()->assertNoContent();
    ($this->plan)(['orderIds' => [($this->order)()->id]])->assertOk();

    // La tournee passe entre d'autres mains : le drapeau ne vaut plus rien
    // pour nous, et son contenu redevient invisible.
    $other = User::factory()->create();
    $this->tour->forceFill(['locked_by' => $other->id])->save();

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours?withStops=1&includePending=1')->assertOk();

    expect($response->json('data.0.stops'))->toBe([]);
});
