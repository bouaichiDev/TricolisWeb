<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Orders\Models\Order;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->configuration = CustomerExportConfiguration::factory()->forCustomer($this->customer)->create();

    $this->payload = fn (array $o = []): array => array_merge([
        'configurationId' => $this->configuration->id,
        'status' => 'pending',
    ], $o);
});

describe('export job creation', function (): void {
    it('creates a job and derives the customer from the configuration', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/export-jobs', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.customerId', $this->customer->id)
            ->assertJsonPath('data.attemptCount', 0);

        $this->assertDatabaseHas('export_jobs', [
            'id' => $response->json('data.id'),
            'customer_id' => $this->customer->id,
        ]);
    });

    it('ignores a customer supplied by the caller', function (): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/export-jobs', ($this->payload)(['customerId' => $otherCustomer->id]))
            ->assertCreated()
            ->assertJsonPath('data.customerId', $this->customer->id);
    });

    it('refuses an inactive configuration', function (): void {
        $inactive = CustomerExportConfiguration::factory()->forCustomer($this->customer)->inactive()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/export-jobs', ($this->payload)(['configurationId' => $inactive->id]))
            ->assertStatus(422)->assertJsonValidationErrors('configurationId');
    });

    it('refuses a configuration from another organization', function (): void {
        $foreign = CustomerExportConfiguration::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/export-jobs', ($this->payload)(['configurationId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('configurationId');
    });

    it('accepts a known morph alias as entity type', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/export-jobs', ($this->payload)([
                'entityType' => 'order', 'entityId' => $order->id,
            ]))
            ->assertCreated()->assertJsonPath('data.entityType', 'order');
    });

    it('refuses an unknown entity type and a php class name', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/export-jobs', ($this->payload)([
                'entityType' => 'inconnu', 'entityId' => '01JC0000000000000000000001',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('entityType');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/export-jobs', ($this->payload)([
                'entityType' => 'App\\Modules\\Orders\\Models\\Order',
                'entityId' => '01JC0000000000000000000001',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('entityType');
    });

    it('ignores processing fields supplied by the caller', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/export-jobs', ($this->payload)([
                'fileName' => 'force.csv',
                'storagePath' => '/tmp/force.csv',
                'attemptCount' => 42,
                'sentAt' => '2026-01-01T00:00:00Z',
            ]))
            ->assertCreated();

        expect($response->json('data.fileName'))->toBeNull()
            ->and($response->json('data.storagePath'))->toBeNull()
            ->and($response->json('data.attemptCount'))->toBe(0)
            ->and($response->json('data.sentAt'))->toBeNull();
    });
});

describe('export job retry', function (): void {
    it('increments the attempt count and clears the error', function (): void {
        $job = ExportJob::factory()->forConfiguration($this->configuration)->failed()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/export-jobs/{$job->id}/retry", ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.attemptCount', 2)
            ->assertJsonPath('data.errorMessage', null)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('audit_logs', ['action' => 'export_job.retried', 'entity_id' => $job->id]);
    });

    it('refuses to retry an export already sent', function (): void {
        $job = ExportJob::factory()->forConfiguration($this->configuration)->sent()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/export-jobs/{$job->id}/retry", ['status' => 'pending'])
            ->assertStatus(409);

        expect($job->fresh()->attempt_count)->toBe(0);
    });

    it('requires a status on retry', function (): void {
        $job = ExportJob::factory()->forConfiguration($this->configuration)->failed()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/export-jobs/{$job->id}/retry", [])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    });
});

describe('export job immutability and scope', function (): void {
    it('exposes no PATCH nor DELETE route', function (): void {
        $job = ExportJob::factory()->forConfiguration($this->configuration)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/export-jobs/{$job->id}", ['status' => 'sent'])->assertStatus(405);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/export-jobs/{$job->id}")->assertStatus(405);

        $this->assertDatabaseHas('export_jobs', ['id' => $job->id]);
    });

    it('hides a job from another organization', function (): void {
        $foreign = ExportJob::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/export-jobs/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/export-jobs/{$foreign->id}/retry", ['status' => 'pending'])->assertNotFound();
    });

    it('lists newest first and filters', function (): void {
        ExportJob::factory()->forConfiguration($this->configuration)->create([
            'generated_at' => '2026-09-01 08:00:00', 'status' => 'failed',
        ]);
        ExportJob::factory()->forConfiguration($this->configuration)->create([
            'generated_at' => '2026-09-05 08:00:00',
        ]);
        ExportJob::factory(2)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/export-jobs')->assertOk()->assertJsonCount(2, 'data');

        expect($response->json('data.0.generatedAt'))->toStartWith('2026-09-05');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/export-jobs?status=failed')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/export-jobs?configurationId={$this->configuration->id}")
            ->assertOk()->assertJsonCount(2, 'data');
    });

    it('audits creation', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/export-jobs', ($this->payload)())->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'export_job.created',
            'entity_type' => 'export_job',
            'entity_id' => $response->json('data.id'),
        ]);
    });
});
