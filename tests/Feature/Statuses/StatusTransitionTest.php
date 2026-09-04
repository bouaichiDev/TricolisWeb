<?php

declare(strict_types=1);

use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Models\StatusTransition;
use App\Shared\Database\MorphMap;

/**
 * Gestion du cycle de vie depuis l'API.
 *
 * L'ensemble des transitions au départ d'un statut est remplacé d'un bloc : une
 * mise à jour arête par arête laisserait, le temps de la séquence, un graphe que
 * personne n'a voulu.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->draft = Status::where('source', MorphMap::ORDER)->where('code', 'draft')->firstOrFail();
    $this->ready = Status::where('source', MorphMap::ORDER)->where('code', 'ready')->firstOrFail();
});

it('liste les transitions au départ d’un statut', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/statuses/{$this->draft->id}/transitions")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('réserve le dessin du cycle de vie à la plateforme', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->putJson("/api/v1/statuses/{$this->draft->id}/transitions", ['transitions' => []])
        ->assertForbidden();
});

it('remplace l’ensemble des transitions d’un bloc', function (): void {
    makePlatformAdmin($this->user);

    $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
        ->putJson("/api/v1/statuses/{$this->draft->id}/transitions", [
            'transitions' => [['toStatusId' => $this->ready->id, 'isManual' => true]],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.toStatusId', $this->ready->id);

    // Les deux transitions d'origine ont disparu, remplacées par la seule
    // envoyée.
    expect(StatusTransition::where('from_status_id', $this->draft->id)->count())->toBe(1);
});

it('vide le cycle de vie quand la liste est vide', function (): void {
    makePlatformAdmin($this->user);

    $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
        ->putJson("/api/v1/statuses/{$this->draft->id}/transitions", ['transitions' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('refuse qu’un statut mène à lui-même', function (): void {
    makePlatformAdmin($this->user);

    $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
        ->putJson("/api/v1/statuses/{$this->draft->id}/transitions", [
            'transitions' => [['toStatusId' => $this->draft->id]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('transitions.0.toStatusId');
});

/** Une transition relie deux statuts de la même entité. */
it('refuse une cible appartenant à une autre entité', function (): void {
    makePlatformAdmin($this->user);
    $other = Status::where('source', MorphMap::USER)->firstOrFail();

    $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
        ->putJson("/api/v1/statuses/{$this->draft->id}/transitions", [
            'transitions' => [['toStatusId' => $other->id]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('transitions.0.toStatusId');
});

it('refuse deux fois la même cible', function (): void {
    makePlatformAdmin($this->user);

    $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
        ->putJson("/api/v1/statuses/{$this->draft->id}/transitions", [
            'transitions' => [
                ['toStatusId' => $this->ready->id],
                ['toStatusId' => $this->ready->id],
            ],
        ])
        ->assertStatus(422);
});

it('conserve le drapeau manuel de chaque transition', function (): void {
    makePlatformAdmin($this->user);

    $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
        ->putJson("/api/v1/statuses/{$this->draft->id}/transitions", [
            'transitions' => [['toStatusId' => $this->ready->id, 'isManual' => false]],
        ])
        ->assertOk()
        ->assertJsonPath('data.0.isManual', false);
});
