<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Services\PlatformAccess;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

describe('permissions', function (): void {
    /**
     * Le référentiel est global, la réponse ne l'est pas.
     *
     * L'appelant est propriétaire d'un organisme : il voit tout sauf les
     * permissions réservées à la plateforme. Renvoyer l'intégralité du
     * référentiel lui donnerait de quoi les proposer dans un formulaire de rôle.
     */
    it('lists the permissions the caller may delegate', function (): void {
        $delegable = Permission::whereNotIn('code', PlatformAccess::PLATFORM_PERMISSIONS)->count();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonCount($delegable, 'data');
    });

    it('filters permissions by module', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/permissions?module=agencies')
            ->assertOk();

        expect(collect($response->json('data'))->pluck('module')->unique()->all())->toBe(['agencies']);
    });

    it('shows a single permission', function (): void {
        $permission = Permission::where('code', 'audit.view')->firstOrFail();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/permissions/{$permission->id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'audit.view');
    });

    it('does not expose any write endpoint on the catalogue', function (): void {
        $permission = Permission::where('code', 'audit.view')->firstOrFail();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/permissions', ['code' => 'forged.permission'])
            ->assertStatus(405);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/permissions/{$permission->id}")
            ->assertStatus(405);
    });

    it('requires an organization context', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/permissions')
            ->assertForbidden();
    });
});
