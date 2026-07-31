<?php

use App\Modules\Organizations\Enums\SubscriptionStatus;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\Subscription;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

describe('subscription', function (): void {
    it('returns 404 while no subscription has been taken', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/subscription')
            ->assertNotFound();
    });

    it('subscribes the active organization', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/subscription', [
                'planCode' => 'business',
                'startsAt' => now()->toDateString(),
                'endsAt' => now()->addYear()->toDateString(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.planCode', 'business')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.grantsAccess', true);

        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $this->organization->id,
            'plan_code' => 'business',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'created',
            'entity_type' => 'subscription',
        ]);
    });

    it('refuses a second subscription for the same organization', function (): void {
        Subscription::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/subscription', ['planCode' => 'starter'])
            ->assertStatus(409);
    });

    it('rejects an end date before the start date', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/subscription', [
                'planCode' => 'starter',
                'startsAt' => now()->toDateString(),
                'endsAt' => now()->subMonth()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('endsAt');
    });

    it('rejects an unknown status', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/subscription', ['planCode' => 'starter', 'status' => 'whatever'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    });

    it('shows the subscription of the active organization', function (): void {
        Subscription::factory()->trialing()->forOrganization($this->organization)->create(['plan_code' => 'starter']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.planCode', 'starter')
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.onTrial', true);
    });

    it('audits a status change as such', function (): void {
        Subscription::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson('/api/v1/subscription', ['status' => SubscriptionStatus::SUSPENDED->value])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.grantsAccess', false);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'status_changed',
            'entity_type' => 'subscription',
        ]);
    });

    it('reports an expired subscription as not granting access', function (): void {
        Subscription::factory()->expired()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.hasEnded', true)
            ->assertJsonPath('data.grantsAccess', false);
    });

    it('deletes the subscription', function (): void {
        $subscription = Subscription::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson('/api/v1/subscription')
            ->assertNoContent();

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    });

    it('never exposes the subscription of another organization', function (): void {
        $other = Organization::factory()->create();
        Subscription::factory()->forOrganization($other)->create(['plan_code' => 'enterprise']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/subscription')
            ->assertNotFound();

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Organization-Id' => $other->id])
            ->getJson('/api/v1/subscription')
            ->assertForbidden();
    });

    it('requires an organization header', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscription')
            ->assertForbidden();
    });
});
