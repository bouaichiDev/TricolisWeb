<?php

use App\Modules\Audit\Models\AuditLog;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalAfterDelivery;

/**
 * Modification d'une réclamation ouverte par le client (§11).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->claim = CustomerPortalAfterDelivery::claim('ikea', ['title' => 'Ancien titre']);
});

function patchClaim(string $id, array $payload, string $email = 'orders@ikea.test'): TestResponse
{
    return test()->withHeaders(CustomerPortal::headers($email))->patchJson("/api/v1/customer/claims/{$id}", $payload);
}

it('lets the customer correct the title, description and type of an open claim', function (): void {
    patchClaim($this->claim->id, ['title' => 'Nouveau titre', 'description' => 'Précision', 'claimType' => 'delay'])->assertOk()
        ->assertJsonPath('data.title', 'Nouveau titre')
        ->assertJsonPath('data.claimType', 'delay');

    expect(AuditLog::where('action', 'customer_portal.claim_updated')->where('entity_id', $this->claim->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'claim.updated')->where('entity_id', $this->claim->id)->exists())->toBeTrue();
});

it('refuses to edit a claim the carrier closed', function (): void {
    $this->claim->update(['closed_at' => now()]);

    patchClaim($this->claim->id, ['title' => 'Trop tard'])->assertStatus(409)->assertJsonPath('code', 'CLAIM_NOT_EDITABLE');
    expect($this->claim->fresh()->title)->toBe('Ancien titre');
});

it('refuses every field the carrier manages, and a move to another order', function (string $field, mixed $value): void {
    patchClaim($this->claim->id, [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'status' => ['status', 'closed'],
    'closedAt' => ['closedAt', '2026-01-01'],
    'cost' => ['cost', 0],
    'decision' => ['decision', 'Accepté'],
    'responsibleUserId' => ['responsibleUserId', '01J0000000000000000000000X'],
    'orderId' => ['orderId', '01J0000000000000000000000X'],
    'orderServiceId' => ['orderServiceId', '01J0000000000000000000000X'],
]);

it('answers 404 for the claim of another customer', function (): void {
    $qoqa = CustomerPortalAfterDelivery::claim('qoqa', ['title' => 'QOQA']);

    patchClaim($qoqa->id, ['title' => 'Piraté'], 'admin@ikea.test')->assertNotFound();
    expect($qoqa->fresh()->title)->toBe('QOQA');
});

it('requires the update permission', function (): void {
    patchClaim($this->claim->id, ['title' => 'Lecteur'], 'viewer@ikea.test')->assertForbidden();
});
