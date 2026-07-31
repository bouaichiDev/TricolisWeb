<?php

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    Storage::fake('local');
});

it('audits document creation and exposes a read-only filtered log', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->post('/api/v1/documents', ['file' => UploadedFile::fake()->create('audit.pdf', 10, 'application/pdf'), 'documentType' => 'proof', 'status' => 'active'])->assertCreated();
    $this->assertDatabaseHas('audit_logs', ['organization_id' => $this->organization->id, 'action' => 'created', 'entity_type' => 'document']);
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->getJson('/api/v1/audit-logs?action=created')->assertOk()->assertJsonCount(1, 'data');
});

it('audits a role assignment on the organization membership', function (): void {
    $role = Role::factory()->forOrganization($this->organization)->create();
    $membership = OrganizationUser::factory()->forOrganization($this->organization)->create();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/organization-users/{$membership->id}", ['roleIds' => [$role->id]])
        ->assertOk();

    $this->assertDatabaseHas('user_roles', ['organization_user_id' => $membership->id, 'role_id' => $role->id]);
    $this->assertDatabaseHas('audit_logs', [
        'organization_id' => $this->organization->id,
        'entity_type' => 'organization_user',
        'entity_id' => $membership->id,
    ]);
});

it('audits a customer status change with its previous value', function (): void {
    $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'status' => CustomerStatus::ACTIVE]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/customers/{$customer->id}/status", ['status' => CustomerStatus::BLOCKED->value])
        ->assertOk();

    $log = AuditLog::where('action', 'status_changed')->where('entity_id', $customer->id)->firstOrFail();

    expect($log->old_values['status'])->toBe(CustomerStatus::ACTIVE->value)
        ->and($log->new_values['status'])->toBe(CustomerStatus::BLOCKED->value)
        ->and($log->entity_type)->toBe('customer');
});

it('redacts sensitive audit values', function (): void {
    app(WriteAuditLog::class)->execute($this->organization->id, $this->user, 'updated', $this->organization, null, ['password' => 'secret', 'name' => 'Visible']);
    $log = AuditLog::latest('created_at')->firstOrFail();
    expect($log->new_values['password'])->toBe('[REDACTED]')->and($log->new_values['name'])->toBe('Visible');
});
