<?php

declare(strict_types=1);

use App\Modules\Identity\Services\PlatformAccess;
use App\Modules\Orders\Models\Order;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Services\StatusSources;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des statuts, commun à toute la plateforme.
 *
 * Deux invariants portent tout le reste : la liste des entités est **dérivée**
 * de la morph map, et l'écriture est **réservée à la plateforme**.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    StatusSources::flush();
});

describe('sources', function (): void {
    it('dérive les entités des tables portant réellement une colonne status', function (): void {
        $sources = StatusSources::all();

        expect($sources)->not->toBeEmpty();

        foreach ($sources as $source) {
            $class = MorphMap::class($source);

            expect($class)->not->toBeNull("L'alias {$source} doit exister dans la morph map");
            expect(Schema::hasColumn((new $class)->getTable(), 'status'))->toBeTrue();
        }
    });

    it('écarte une table de liaison dépourvue de statut', function (): void {
        expect(StatusSources::all())->not->toContain(MorphMap::PACKAGE_ORDER_LINE);
    });

    it('expose la liste aux membres', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/statuses/sources')
            ->assertOk()
            ->assertJsonFragment([MorphMap::ORDER]);
    });
});

describe('lecture', function (): void {
    it('laisse tout membre lire le référentiel', function (): void {
        Status::factory()->create(['source' => MorphMap::ORDER, 'code' => 'archived', 'status' => 900]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/statuses?source='.MorphMap::ORDER.'&search=archived')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'archived');
    });

    it('sème les statuts issus des énumérations existantes', function (): void {
        expect(Status::where('source', MorphMap::ORDER)->count())->toBe(10)
            ->and(Status::where('source', MorphMap::ORDER_SERVICE)->count())->toBe(9);
    });
});

describe('écriture', function (): void {
    it('refuse la création à un administrateur d’organisation', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/statuses', [
                'source' => MorphMap::ORDER,
                'status' => 990,
                'code' => 'archived',
                'label' => 'Archivée',
            ])
            ->assertForbidden();
    });

    it('accepte la création par la plateforme', function (): void {
        makePlatformAdmin($this->user);

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/statuses', [
                'source' => MorphMap::ORDER,
                'status' => 990,
                'code' => 'archived',
                'label' => 'Archivée',
                'icon' => 'Archive',
                'isToSend' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'archived')
            ->assertJsonPath('data.isToSend', true);

        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'status']);
    });

    it('refuse une source qui n’est pas une entité connue', function (): void {
        makePlatformAdmin($this->user);

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/statuses', [
                'source' => 'licorne',
                'status' => 990,
                'code' => 'archived',
                'label' => 'Archivée',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('source');
    });

    /** Le même code existe pour plusieurs entités : c'est `source` qui les sépare. */
    it('accepte le même code sur deux entités différentes', function (): void {
        makePlatformAdmin($this->user);

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/statuses', [
                'source' => MorphMap::PACKAGE,
                'status' => 1,
                'code' => 'draft',
                'label' => 'Brouillon',
            ])
            ->assertCreated();
    });

    it('refuse deux fois le même code pour une même entité', function (): void {
        makePlatformAdmin($this->user);

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/statuses', [
                'source' => MorphMap::ORDER,
                'status' => 990,
                'code' => 'draft',
                'label' => 'Autre brouillon',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    });

    it('refuse deux fois le même identifiant numérique pour une même entité', function (): void {
        makePlatformAdmin($this->user);
        $existing = Status::where('source', MorphMap::ORDER)->firstOrFail();

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/statuses', [
                'source' => MorphMap::ORDER,
                'status' => $existing->status,
                'code' => 'archived',
                'label' => 'Archivée',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    });

    it('réserve la modification et la suppression à la plateforme', function (): void {
        $status = Status::where('source', MorphMap::ORDER)->firstOrFail();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/statuses/{$status->id}", ['label' => 'Autre'])
            ->assertForbidden();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/statuses/{$status->id}")
            ->assertForbidden();
    });

    it('ne laisse pas changer l’entité d’un statut', function (): void {
        makePlatformAdmin($this->user);
        $status = Status::where('source', MorphMap::ORDER)->firstOrFail();

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/statuses/{$status->id}", ['source' => MorphMap::PACKAGE])
            ->assertOk();

        expect($status->fresh()->source)->toBe(MorphMap::ORDER);
    });
});

describe('suppression', function (): void {
    it('supprime un statut que rien ne porte', function (): void {
        makePlatformAdmin($this->user);
        $status = Status::factory()->create(['source' => MorphMap::ORDER, 'code' => 'archived', 'status' => 990]);

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/statuses/{$status->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('statuses', ['id' => $status->id]);
    });

    /**
     * Aucune clé étrangère ne protège ces colonnes : c'est ce contrôle qui
     * empêche des enregistrements d'afficher un code sans libellé.
     */
    it('refuse de supprimer un statut encore porté par des enregistrements', function (): void {
        makePlatformAdmin($this->user);
        Order::factory()
            ->forOrganization($this->organization)
            ->create(['created_by' => $this->user->id, 'status' => 'draft']);

        $status = Status::where('source', MorphMap::ORDER)->where('code', 'draft')->firstOrFail();

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/statuses/{$status->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseHas('statuses', ['id' => $status->id]);
    });
});

/**
 * Les trois permissions d'écriture sont plateforme : un administrateur
 * d'organisme ne peut pas se les déléguer.
 */
it('classe les écritures parmi les permissions plateforme', function (): void {
    expect(PlatformAccess::PLATFORM_PERMISSIONS)
        ->toContain('statuses.create')
        ->toContain('statuses.update')
        ->toContain('statuses.delete')
        ->not->toContain('statuses.view');
});
