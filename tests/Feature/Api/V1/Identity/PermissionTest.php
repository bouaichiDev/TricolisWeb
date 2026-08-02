<?php

use App\Modules\Identity\Models\Permission;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

describe('permissions', function (): void {
    it('lists the global permission catalogue', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonCount(Permission::count(), 'data');
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
