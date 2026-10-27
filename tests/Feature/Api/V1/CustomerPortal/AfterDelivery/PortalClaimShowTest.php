<?php

use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalAfterDelivery;

/**
 * Détail d'une réclamation du portail (§10).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->claim = CustomerPortalAfterDelivery::claim('ikea', [
        'description' => 'Deux cartons ouverts.',
        'cause' => 'Chauffeur pressé', 'decision' => 'Geste commercial', 'follow_up' => 'Rappeler le dépôt',
        'result' => 'Remboursé', 'cost' => 250,
    ]);
});

function showClaim(string $id, string $email = 'admin@ikea.test'): TestResponse
{
    return test()->withHeaders(CustomerPortal::headers($email))->getJson("/api/v1/customer/claims/{$id}");
}

it('shows its own claim with what the member may still do', function (): void {
    showClaim($this->claim->id)->assertOk()
        ->assertJsonPath('data.description', 'Deux cartons ouverts.')
        ->assertJsonPath('data.order.id', $this->claim->order_id)
        ->assertJsonPath('data.actions.canEdit', true)
        ->assertJsonPath('data.actions.canAttach', true);
});

it('keeps every internal treatment note out of the response', function (): void {
    $body = json_encode(showClaim($this->claim->id)->json(), JSON_UNESCAPED_UNICODE);

    foreach (['Chauffeur pressé', 'Geste commercial', 'Rappeler le dépôt', 'Remboursé', '"cost"', 'responsibleUserId', 'createdBy', 'organizationId'] as $internal) {
        expect($body)->not->toContain($internal);
    }
});

it('answers 404 for the claim of another customer, an unknown or an invalid id', function (string $id): void {
    showClaim($id)->assertNotFound();
})->with([
    'qoqa' => fn (): string => CustomerPortalAfterDelivery::claim('qoqa')->id,
    'unknown ulid' => '01J0000000000000000000000X',
    'invalid' => 'pas-un-ulid',
]);

it('offers no edit on a closed claim, nor to a reader', function (): void {
    $this->claim->update(['closed_at' => now()]);

    showClaim($this->claim->id)->assertJsonPath('data.actions.canEdit', false)->assertJsonPath('data.actions.canAttach', false);
    showClaim(CustomerPortalAfterDelivery::claim('ikea')->id, 'viewer@ikea.test')->assertJsonPath('data.actions.canEdit', false);
});

it('refuses an unauthenticated request', function (): void {
    $this->getJson("/api/v1/customer/claims/{$this->claim->id}")->assertUnauthorized();
});
