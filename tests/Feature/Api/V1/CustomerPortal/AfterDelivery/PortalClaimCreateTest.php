<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Claims\Models\Claim;
use App\Modules\Statuses\Models\Status;
use App\Shared\Database\MorphMap;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Création d'une réclamation depuis le portail (§6, §31).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->order = CustomerPortalOrders::order('ikea');
    $this->service = $this->order->orderServices()->first();
    $this->payload = ['orderId' => $this->order->id, 'orderServiceId' => $this->service->id, 'title' => 'Colis endommagé', 'description' => 'Carton ouvert.', 'claimType' => 'damage'];
});

function createClaim(array $payload, string $email = 'admin@ikea.test'): TestResponse
{
    return test()->withHeaders(CustomerPortal::headers($email))->postJson('/api/v1/customer/claims', $payload);
}

it('creates a claim on its own order and service, the rest imposed by the backend', function (): void {
    $id = createClaim($this->payload)->assertCreated()
        ->assertJsonPath('data.title', 'Colis endommagé')
        ->assertJsonPath('data.order.reference', $this->order->order_number)
        ->assertJsonPath('data.orderService.id', $this->service->id)
        ->assertJsonPath('data.status.code', 'open')
        ->assertJsonPath('data.status.label', 'Ouverte')
        ->assertJsonPath('data.isClosed', false)
        ->json('data.id');

    $claim = Claim::findOrFail($id);
    $member = CustomerPortal::member('admin@ikea.test');

    expect($claim->customer_id)->toBe($member->customer_id)
        ->and($claim->organization_id)->toBe($this->order->organization_id)
        ->and($claim->created_by)->toBe($member->user_id)
        ->and($claim->responsible_user_id)->toBeNull()
        ->and(AuditLog::where('action', 'customer_portal.claim_created')->where('entity_id', $id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'claim.created')->where('entity_id', $id)->exists())->toBeTrue();
});

it('creates a claim without any order, as the diagram allows', function (): void {
    createClaim(['title' => 'Facture illisible', 'claimType' => 'other'])->assertCreated()->assertJsonPath('data.order', null);
});

it('refuses the order or the service of another customer', function (): void {
    $qoqa = CustomerPortalOrders::order('qoqa');

    createClaim([...$this->payload, 'orderId' => $qoqa->id, 'orderServiceId' => null])->assertUnprocessable()->assertJsonValidationErrors('orderId');
    createClaim([...$this->payload, 'orderServiceId' => $qoqa->orderServices()->value('id')])->assertUnprocessable()->assertJsonValidationErrors('orderServiceId');

    expect(Claim::count())->toBe(0);
});

it('requires the order of a service', function (): void {
    createClaim([...$this->payload, 'orderId' => null])->assertUnprocessable()->assertJsonValidationErrors('orderId');
});

it('refuses every internal field injected in the payload', function (string $field, mixed $value): void {
    createClaim([...$this->payload, $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Claim::count())->toBe(0);
})->with([
    'customerId' => ['customerId', '01J0000000000000000000000X'],
    'organizationId' => ['organizationId', '01J0000000000000000000000X'],
    'responsibleUserId' => ['responsibleUserId', '01J0000000000000000000000X'],
    'status' => ['status', 'closed'],
    'cost' => ['cost', 999],
    'decision' => ['decision', 'Remboursé'],
    'cause' => ['cause', 'Chauffeur'],
    'closedAt' => ['closedAt', '2026-01-01'],
    'tourId' => ['tourId', '01J0000000000000000000000X'],
]);

it('validates the payload', function (): void {
    createClaim(['orderId' => 'pas-un-ulid'])->assertUnprocessable()->assertJsonValidationErrors(['orderId', 'title', 'claimType']);
    createClaim([...$this->payload, 'title' => str_repeat('x', 256)])->assertUnprocessable()->assertJsonValidationErrors('title');
});

it('refuses cleanly when the carrier configured no initial claim status', function (): void {
    Status::where('source', MorphMap::CLAIM)->update(['is_default' => false]);

    createClaim($this->payload)->assertStatus(409)->assertJsonPath('code', 'CLAIM_STATUS_NOT_CONFIGURED');
});

it('requires the create permission', function (): void {
    createClaim($this->payload, 'viewer@ikea.test')->assertForbidden();
});
