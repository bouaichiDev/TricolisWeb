<?php

declare(strict_types=1);

use App\Modules\Orders\Models\Order;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Models\StatusTransition;
use App\Modules\Statuses\Services\StatusMachine;
use App\Shared\Database\MorphMap;

/**
 * La machine à états lit la base, plus l'énumération.
 *
 * C'est tout l'enjeu : ce que l'administrateur dessine dans le référentiel doit
 * gouverner ce que l'API accepte. Ces tests modifient la table et vérifient que
 * le comportement suit.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->machine = app(StatusMachine::class);

    $this->order = Order::factory()
        ->forOrganization($this->organization)
        ->create(['created_by' => $this->user->id, 'status' => 'draft']);
});

/** Le semis reproduit exactement l'ancienne machine figée dans le code. */
it('reprend le cycle de vie des commandes tel qu’il était', function (): void {
    expect(StatusTransition::count())->toBe(20);

    $codes = array_map(
        static fn (Status $status): string => $status->code,
        $this->machine->transitionsFrom(MorphMap::ORDER, 'draft'),
    );

    expect($codes)->toEqualCanonicalizing(['confirmed', 'cancelled']);
});

it('distingue une transition existante d’une transition posable à la main', function (): void {
    // « prête → planifiée » existe, mais c'est la planification qui la pose.
    expect($this->machine->allows(MorphMap::ORDER, 'ready', 'planned'))->toBeTrue()
        ->and($this->machine->allowsManually(MorphMap::ORDER, 'ready', 'planned'))->toBeFalse();

    $codes = array_map(
        static fn (Status $status): string => $status->code,
        $this->machine->transitionsFrom(MorphMap::ORDER, 'ready'),
    );

    expect($codes)->not->toContain('planned');
});

it('expose au client les transitions lues en base', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$this->order->id}")
        ->assertOk()
        ->assertJsonPath('data.allowedTransitions', ['confirmed', 'cancelled']);
});

/** Le cœur du sujet : ajouter une arête change ce que l'API propose. */
it('suit une transition ajoutée au référentiel', function (): void {
    $draft = Status::where('source', MorphMap::ORDER)->where('code', 'draft')->firstOrFail();
    $ready = Status::where('source', MorphMap::ORDER)->where('code', 'ready')->firstOrFail();

    StatusTransition::create([
        'from_status_id' => $draft->id,
        'to_status_id' => $ready->id,
        'is_manual' => true,
    ]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$this->order->id}")
        ->assertOk()
        ->assertJsonPath('data.allowedTransitions', ['confirmed', 'ready', 'cancelled']);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/orders/{$this->order->id}/status", ['status' => 'ready'])
        ->assertOk()
        ->assertJsonPath('data.status', 'ready');
});

it('refuse une transition retirée du référentiel', function (): void {
    $draft = Status::where('source', MorphMap::ORDER)->where('code', 'draft')->firstOrFail();
    $confirmed = Status::where('source', MorphMap::ORDER)->where('code', 'confirmed')->firstOrFail();

    StatusTransition::where('from_status_id', $draft->id)
        ->where('to_status_id', $confirmed->id)
        ->delete();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/orders/{$this->order->id}/status", ['status' => 'confirmed'])
        ->assertStatus(422)
        // Le message ne nomme plus « confirmed » : il lit le referentiel.
        ->assertJsonPath('errors.status.0', 'Transition impossible de « Brouillon » vers « Confirmée ». Statuts atteignables : cancelled.');
});

it('masque un statut désactivé sans supprimer sa transition', function (): void {
    Status::where('source', MorphMap::ORDER)->where('code', 'cancelled')->update(['active' => false]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$this->order->id}")
        ->assertOk()
        ->assertJsonPath('data.allowedTransitions', ['confirmed']);
});

/** Le gel du contenu vient lui aussi du référentiel. */
it('suit le drapeau de modification du contenu', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$this->order->id}")
        ->assertJsonPath('data.allowsContentChanges', true);

    Status::where('source', MorphMap::ORDER)
        ->where('code', 'draft')
        ->update(['allows_content_changes' => false]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$this->order->id}")
        ->assertJsonPath('data.allowsContentChanges', false);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->deleteJson("/api/v1/orders/{$this->order->id}")
        ->assertStatus(409);
});

it('exige un motif quand le référentiel le demande', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/orders/{$this->order->id}/status", ['status' => 'cancelled'])
        ->assertStatus(422);

    Status::where('source', MorphMap::ORDER)
        ->where('code', 'cancelled')
        ->update(['requires_reason' => false]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/orders/{$this->order->id}/status", ['status' => 'cancelled'])
        ->assertOk();
});

/**
 * Supprimer un statut emporte ses transitions : une arête vers un statut
 * disparu bloquerait la commande sans rien expliquer.
 */
it('supprime les transitions avec le statut', function (): void {
    $cancelled = Status::where('source', MorphMap::ORDER)->where('code', 'cancelled')->firstOrFail();
    $before = StatusTransition::count();

    $cancelled->delete();

    expect(StatusTransition::count())->toBeLessThan($before)
        ->and(StatusTransition::where('to_status_id', $cancelled->id)->count())->toBe(0);
});
