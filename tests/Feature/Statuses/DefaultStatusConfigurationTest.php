<?php

declare(strict_types=1);

use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Services\StatusSources;
use App\Shared\Database\MorphMap;

/**
 * Choisir le statut qu'une entité reçoit à sa création.
 *
 * La liste des entités n'est écrite nulle part : c'est celle du référentiel.
 * Une entité ajoutée au code apparaît dans la configuration d'elle-même.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    StatusSources::flush();

    $this->put = fn (string $source, ?string $statusId) => $this->actingAs($this->user->fresh(), 'sanctum')
        ->withHeaders($this->headers)
        ->putJson("/api/v1/statuses/defaults/{$source}", ['statusId' => $statusId]);

    $this->statusOf = fn (string $source, string $code): Status => Status::where('source', $source)
        ->where('code', $code)->firstOrFail();
});

describe('la liste', function (): void {
    it('présente chaque entité qui porte un statut, sans en nommer aucune', function (): void {
        $rows = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/statuses/defaults')->assertOk()->json('data');

        expect(array_column($rows, 'source'))->toBe(StatusSources::all());
    });

    /** Rien n'est coché : l'écran doit dire ce que la base mettra d'elle-même. */
    it('donne la valeur de la colonne quand rien n’est choisi', function (): void {
        $rows = collect($this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/statuses/defaults')->json('data'))->keyBy('source');

        expect($rows[MorphMap::ORDER]['statusId'])->toBeNull()
            ->and($rows[MorphMap::ORDER]['systemDefault'])->toBe('draft')
            ->and($rows[MorphMap::ORDER_LINE]['systemDefault'])->toBe('active')
            ->and(array_column($rows[MorphMap::ORDER]['options'], 'code'))->toContain('confirmed');
    });
});

describe('le choix', function (): void {
    it('coche un statut et décoche l’ancien de la même entité', function (): void {
        makePlatformAdmin($this->user);
        $pending = ($this->statusOf)(MorphMap::ORDER_SERVICE, 'pending');
        $ready = ($this->statusOf)(MorphMap::ORDER_SERVICE, 'ready_to_plan');

        ($this->put)(MorphMap::ORDER_SERVICE, $pending->id)->assertOk();
        $response = ($this->put)(MorphMap::ORDER_SERVICE, $ready->id)->assertOk();

        expect($pending->fresh()->is_default)->toBeFalse()
            ->and($ready->fresh()->is_default)->toBeTrue()
            ->and(collect($response->json('data'))->firstWhere('source', MorphMap::ORDER_SERVICE)['code'])
            ->toBe('ready_to_plan');
    });

    it('ne touche pas au choix d’une autre entité', function (): void {
        makePlatformAdmin($this->user);
        $order = ($this->statusOf)(MorphMap::ORDER, 'confirmed');

        ($this->put)(MorphMap::ORDER, $order->id)->assertOk();
        ($this->put)(MorphMap::ORDER_SERVICE, ($this->statusOf)(MorphMap::ORDER_SERVICE, 'pending')->id)->assertOk();

        expect($order->fresh()->is_default)->toBeTrue();
    });

    it('retire le choix avec null', function (): void {
        makePlatformAdmin($this->user);
        $confirmed = ($this->statusOf)(MorphMap::ORDER, 'confirmed');

        ($this->put)(MorphMap::ORDER, $confirmed->id)->assertOk();
        ($this->put)(MorphMap::ORDER, null)->assertOk();

        expect(Status::where('source', MorphMap::ORDER)->where('is_default', true)->exists())->toBeFalse();
    });

    it('refuse le statut d’une autre entité', function (): void {
        makePlatformAdmin($this->user);

        ($this->put)(MorphMap::ORDER, ($this->statusOf)(MorphMap::ORDER_SERVICE, 'pending')->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statusId');
    });

    it('refuse un statut désactivé', function (): void {
        makePlatformAdmin($this->user);
        $inactive = Status::factory()->forSource(MorphMap::ORDER)->create(['active' => false]);

        ($this->put)(MorphMap::ORDER, $inactive->id)->assertUnprocessable();
    });

    /** Désactiver le statut par défaut ne laisse pas un choix que la création ignorerait. */
    it('perd sa place quand on le désactive', function (): void {
        makePlatformAdmin($this->user);
        $confirmed = ($this->statusOf)(MorphMap::ORDER, 'confirmed');
        ($this->put)(MorphMap::ORDER, $confirmed->id)->assertOk();

        $this->actingAs($this->user->fresh(), 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/statuses/{$confirmed->id}", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.isDefault', false);

        expect($confirmed->fresh()->is_default)->toBeFalse();
    });

    it('répond 404 pour une entité sans statut', function (): void {
        makePlatformAdmin($this->user);

        ($this->put)('inconnue', null)->assertNotFound();
    });

    /** Un statut décrit le domaine de toute la plateforme, pas un organisme. */
    it('réserve le choix à la plateforme', function (): void {
        ($this->put)(MorphMap::ORDER, ($this->statusOf)(MorphMap::ORDER, 'confirmed')->id)->assertForbidden();
    });
});
