<?php

declare(strict_types=1);

use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Models\StatusPropagation;
use App\Shared\Database\MorphMap;

/**
 * Déclarer ce qu'un statut entraîne.
 *
 * Les règles se lisent par tous — elles expliquent pourquoi une commande a
 * changé de statut toute seule — et ne s'écrivent que par la plateforme, comme
 * le reste du référentiel.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->status = fn (string $source, string $code): Status => Status::where('source', $source)
        ->where('code', $code)->first()
        ?? Status::factory()->forSource($source)->create(['code' => $code, 'label' => $code]);

    $this->post = fn (array $payload) => $this->actingAs($this->user->fresh(), 'sanctum')
        ->withHeaders($this->headers)
        ->postJson('/api/v1/status-propagations', $payload);

    /** La même paire, écrite comme la table l'attend. */
    $this->declare = fn (string $fromSource, string $fromCode, string $toSource, string $toCode): StatusPropagation => StatusPropagation::create([
        'from_status_id' => ($this->status)($fromSource, $fromCode)->id,
        'to_status_id' => ($this->status)($toSource, $toCode)->id,
    ]);

    $this->pair = fn (string $fromSource, string $fromCode, string $toSource, string $toCode): array => [
        'fromStatusId' => ($this->status)($fromSource, $fromCode)->id,
        'toStatusId' => ($this->status)($toSource, $toCode)->id,
    ];
});

describe('lecture', function (): void {
    it('donne les deux statuts en clair, sans redemander le référentiel', function (): void {
        ($this->declare)(MorphMap::PACKAGE, 'loaded', MorphMap::ORDER_SERVICE, 'in_progress');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/status-propagations')
            ->assertOk()
            ->assertJsonPath('data.rules.0.from.source', MorphMap::PACKAGE)
            ->assertJsonPath('data.rules.0.from.code', 'loaded')
            ->assertJsonPath('data.rules.0.to.source', MorphMap::ORDER_SERVICE)
            ->assertJsonPath('data.rules.0.mode', 'all')
            // La hiérarchie accompagne les règles : l'écran n'a pas à la redécrire.
            ->assertJsonPath('data.hierarchy.'.MorphMap::ORDER_SERVICE, [MorphMap::PACKAGE]);
    });
});

describe('écriture', function (): void {
    it('refuse la déclaration à un administrateur d’organisation', function (): void {
        ($this->post)(($this->pair)(MorphMap::PACKAGE, 'loaded', MorphMap::ORDER_SERVICE, 'in_progress'))
            ->assertForbidden();
    });

    it('accepte la déclaration par la plateforme', function (): void {
        makePlatformAdmin($this->user);

        ($this->post)(($this->pair)(MorphMap::PACKAGE, 'loaded', MorphMap::ORDER_SERVICE, 'in_progress') + ['mode' => 'any'])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'any')
            ->assertJsonPath('data.active', true);
    });

    /**
     * Une règle entre deux entités sans lien serait enregistrée et ne ferait
     * jamais rien — pire qu'un refus, puisque personne ne saurait pourquoi.
     */
    it('refuse deux entités qui ne se contiennent pas', function (): void {
        makePlatformAdmin($this->user);

        ($this->post)(($this->pair)(MorphMap::VEHICLE, 'maintenance', MorphMap::ORDER, 'cancelled'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('toStatusId');
    });

    it('refuse deux fois la même paire', function (): void {
        makePlatformAdmin($this->user);
        $pair = ($this->pair)(MorphMap::ORDER_SERVICE, 'completed', MorphMap::PACKAGE, 'delivered');

        ($this->post)($pair)->assertCreated();
        ($this->post)($pair)->assertUnprocessable()->assertJsonValidationErrors('toStatusId');
    });

    it('modifie le mode et l’activation', function (): void {
        makePlatformAdmin($this->user);
        $rule = ($this->declare)(MorphMap::PACKAGE, 'loaded', MorphMap::ORDER_SERVICE, 'in_progress');

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/status-propagations/{$rule->id}", ['mode' => 'any', 'active' => false])
            ->assertOk()
            ->assertJsonPath('data.mode', 'any')
            ->assertJsonPath('data.active', false);
    });

    /**
     * Une règle mal visée — le bon statut sur la mauvaise entité — se répare.
     * La supprimer pour la resaisir perdrait sa trace au journal.
     */
    it('corrige la paire d’une règle', function (): void {
        makePlatformAdmin($this->user);
        $rule = ($this->declare)(MorphMap::PACKAGE, 'loaded', MorphMap::ORDER_SERVICE, 'in_progress');

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/status-propagations/{$rule->id}", [
                'toStatusId' => ($this->status)(MorphMap::ORDER_SERVICE, 'planned')->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.to.code', 'planned')
            ->assertJsonPath('data.from.code', 'loaded');
    });

    it('refuse une correction vers une entité non reliée', function (): void {
        makePlatformAdmin($this->user);
        $rule = ($this->declare)(MorphMap::PACKAGE, 'loaded', MorphMap::ORDER_SERVICE, 'in_progress');

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/status-propagations/{$rule->id}", [
                'toStatusId' => ($this->status)(MorphMap::VEHICLE, 'maintenance')->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('toStatusId');
    });

    it('supprime une règle', function (): void {
        makePlatformAdmin($this->user);
        $rule = ($this->declare)(MorphMap::PACKAGE, 'loaded', MorphMap::ORDER_SERVICE, 'in_progress');

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/status-propagations/{$rule->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('status_propagations', ['id' => $rule->id]);
    });

    /** Une règle qui désigne un statut disparu ne veut plus rien dire. */
    it('disparaît avec le statut qu’elle désigne', function (): void {
        $from = Status::factory()->forSource(MorphMap::PACKAGE)->create(['code' => 'loaded']);
        $rule = StatusPropagation::create([
            'from_status_id' => $from->id,
            'to_status_id' => ($this->status)(MorphMap::ORDER_SERVICE, 'in_progress')->id,
        ]);

        $from->delete();

        $this->assertDatabaseMissing('status_propagations', ['id' => $rule->id]);
    });
});
