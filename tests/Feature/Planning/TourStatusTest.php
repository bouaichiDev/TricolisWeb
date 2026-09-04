<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Tours\Models\Tour;
use App\Shared\Database\MorphMap;

/**
 * Le passage d'une tournée d'un état à un autre.
 *
 * Le cycle a été décidé par le propriétaire du projet le 26 août 2026 et semé
 * dans `status_transitions` : brouillon → confirmée → planifiée → en cours →
 * terminée, l'annulation restant ouverte tant que la tournée n'est pas
 * terminée. C'est le référentiel qui décide, pas le code.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);

    $this->tourIn = fn (string $status): Tour => Tour::factory()->forAgency($this->agency)
        ->create(['status' => $status]);

    $this->move = fn (Tour $tour, string $status) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$tour->id}/status", ['status' => $status]);
});

it('walks the whole lifecycle', function (): void {
    $tour = ($this->tourIn)('draft');

    foreach (['confirmed', 'planned', 'in_progress', 'completed'] as $step) {
        ($this->move)($tour, $step)->assertOk()->assertJsonPath('data.status', $step);
        $tour->refresh();
    }

    $this->assertDatabaseHas('tours', ['id' => $tour->id, 'status' => 'completed']);
});

/** Le raccourci n'existe pas : on confirme avant de planifier. */
it('refuses to skip a step', function (): void {
    $tour = ($this->tourIn)('draft');

    ($this->move)($tour, 'planned')
        ->assertStatus(422)->assertJsonValidationErrors('status');

    expect($tour->fresh()->status->value)->toBe('draft');
});

it('cancels a tour that has not run yet', function (): void {
    foreach (['draft', 'confirmed', 'planned', 'in_progress'] as $status) {
        ($this->move)(($this->tourIn)($status), 'cancelled')->assertOk();
    }
});

/** Une tournée achevée ne s'annule pas : elle se conteste. */
it('refuses to cancel a completed tour', function (): void {
    ($this->move)(($this->tourIn)('completed'), 'cancelled')
        ->assertStatus(422)->assertJsonValidationErrors('status');
});

it('refuses a status absent from the referential', function (): void {
    ($this->move)(($this->tourIn)('draft'), 'archived')
        ->assertStatus(422)->assertJsonValidationErrors('status');
});

/** Rejouer la même transition ne casse rien : la seconde ne fait rien. */
it('accepts the status it already has', function (): void {
    $tour = ($this->tourIn)('confirmed');

    ($this->move)($tour, 'confirmed')->assertOk()->assertJsonPath('data.status', 'confirmed');
});

it('writes the change to the audit log', function (): void {
    $tour = ($this->tourIn)('draft');

    ($this->move)($tour, 'confirmed')->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'tour.status_changed',
        'entity_type' => MorphMap::TOUR,
        'entity_id' => $tour->id,
    ]);
});

/**
 * La réservation du brouillon vaut aussi pour sa validation : un collègue ne
 * valide pas la planification d'un autre.
 */
it('refuses the transition to someone else’s draft', function (): void {
    $other = User::factory()->create(['first_name' => 'Sara', 'last_name' => 'Amrani']);
    $tour = ($this->tourIn)('draft');

    AuditLog::create([
        'organization_id' => $this->organization->id,
        'user_id' => $other->id,
        'action' => 'tour.created',
        'entity_type' => MorphMap::TOUR,
        'entity_id' => $tour->id,
        'created_at' => now(),
    ]);

    ($this->move)($tour, 'confirmed')->assertForbidden();
    expect($tour->fresh()->status->value)->toBe('draft');
});

it('hides a tour of another organization', function (): void {
    $foreign = Tour::factory()->create();

    ($this->move)($foreign, 'confirmed')->assertNotFound();
});
