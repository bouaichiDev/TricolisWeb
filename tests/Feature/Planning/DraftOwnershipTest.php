<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Planning\Services\DraftOwnership;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Shared\Database\MorphMap;

/**
 * Tant qu'une tournée est au brouillon, seul son créateur la modifie.
 *
 * Deux planificateurs déplaçant les mêmes arrêts en même temps produiraient une
 * tournée qu'aucun des deux n'a voulue.
 *
 * Le créateur n'est pas une colonne — le diagramme n'en prévoit pas — mais une
 * lecture du journal d'audit. La réservation ne peut donc pas rester coincée :
 * il n'y a rien à relâcher.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->ownership = app(DraftOwnership::class);

    $this->draftBy = function (?string $userId): Tour {
        $tour = Tour::factory()->forAgency($this->agency)->create(['status' => 'draft']);

        if ($userId !== null) {
            AuditLog::create([
                'organization_id' => $this->organization->id,
                'user_id' => $userId,
                'action' => 'tour.created',
                'entity_type' => MorphMap::TOUR,
                'entity_id' => $tour->id,
                // `audit_logs` n'auto-horodate pas : la colonne n'a pas de
                // valeur par defaut, et c'est elle qui ordonne les entrees.
                'created_at' => now(),
            ]);
        }

        return $tour;
    };
});

it('names the creator from the audit log', function (): void {
    $tour = ($this->draftBy)($this->user->id);

    expect($this->ownership->creatorIdOf($tour))->toBe($this->user->id);
    expect($this->ownership->creatorOf($tour)?->id)->toBe($this->user->id);
    expect($this->ownership->canModify($tour, $this->user->id))->toBeTrue();
});

it('lets its creator modify the draft', function (): void {
    $tour = ($this->draftBy)($this->user->id);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/tours/{$tour->id}", ['instructions' => 'Passer par la rocade'])
        ->assertOk();
});

/**
 * Un 403 nommant la personne, jamais un 404 : la tournée existe, elle est
 * visible, elle est simplement en cours de préparation par quelqu'un d'autre.
 */
it('refuses another user and says who is planning', function (): void {
    // Un autre compte, cree pour l'occasion : `authUser()` rend toujours le
    // meme, et le test aurait valide contre lui-meme.
    $other = User::factory()->create(['first_name' => 'Sara', 'last_name' => 'Amrani']);
    $tour = ($this->draftBy)($other->id);

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/tours/{$tour->id}", ['instructions' => 'Tentative'])
        ->assertForbidden();

    expect($response->json('message'))->toContain('Sara Amrani');
});

/** La lecture reste ouverte : voir la planification d'un collègue ne gêne personne. */
it('still lets another user read the draft', function (): void {
    $other = User::factory()->create();
    $tour = ($this->draftBy)($other->id);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/tours/{$tour->id}")->assertOk();
});

it('protects the stops, services and periods of the draft', function (): void {
    $other = User::factory()->create();
    $tour = ($this->draftBy)($other->id);
    $stop = TourStop::factory()->forTour($tour)->create();

    // Charge utile valide : la validation passe avant la garde, et un payload
    // vide aurait rendu 422 sans jamais l'atteindre.
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$tour->id}/stops/reorder", ['ids' => [$stop->id]])
        ->assertForbidden();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->deleteJson("/api/v1/tours/{$tour->id}")
        ->assertForbidden();
});

/**
 * L'exclusivité cesse avec le brouillon : une tournée validée relève des
 * permissions ordinaires, sans quoi son créateur en resterait propriétaire
 * pour toujours.
 */
it('releases the tour once it leaves the draft', function (): void {
    $other = User::factory()->create();
    $tour = ($this->draftBy)($other->id);
    $tour->forceFill(['status' => 'planned'])->save();

    expect($this->ownership->canModify($tour->fresh(), $this->user->id))->toBeTrue();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/tours/{$tour->id}", ['instructions' => 'Après validation'])
        ->assertOk();
});

/** Une tournée créée par un import n'a pas d'auteur : personne ne la réserve. */
it('reserves nothing when the journal names no creator', function (): void {
    $tour = ($this->draftBy)(null);

    expect($this->ownership->creatorIdOf($tour))->toBeNull();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/tours/{$tour->id}", ['instructions' => 'Sans auteur'])
        ->assertOk();
});

/** Une liste de tournées ne pose pas la question ligne par ligne. */
it('reads the creators of many tours in one query', function (): void {
    $first = ($this->draftBy)($this->user->id);
    $second = ($this->draftBy)($this->user->id);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $creators = $this->ownership->creatorsOf([$first->id, $second->id]);

    expect($queries)->toBe(1);
    expect($creators)->toBe([$first->id => $this->user->id, $second->id => $this->user->id]);
});
