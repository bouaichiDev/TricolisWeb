<?php

use App\Modules\CustomerPortal\Support\CustomerPortalRoles;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalAfterDelivery;
use Tests\Support\CustomerPortalTracking;

/**
 * Liste des réclamations du portail (§7).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->damage = CustomerPortalAfterDelivery::claim('ikea', ['title' => 'Carton écrasé', 'claim_type' => 'damage', 'cost' => 120, 'decision' => 'Avoir interne']);
    $this->late = CustomerPortalAfterDelivery::claim('ikea', ['title' => 'Livraison tardive', 'claim_type' => 'delay', 'description' => 'Deux heures de retard', 'created_at' => now()->subDays(3), 'closed_at' => now()->subDay()]);
    $this->qoqa = CustomerPortalAfterDelivery::claim('qoqa', ['title' => 'Réclamation QOQA']);
});

function listClaims(string $query = '', string $email = 'admin@ikea.test'): TestResponse
{
    return test()->withHeaders(CustomerPortal::headers($email))->getJson('/api/v1/customer/claims'.$query);
}

it('lists only the claims of the current customer, paginated', function (): void {
    $response = listClaims()->assertOk()->assertJsonPath('meta.total', 2)->assertJsonStructure(['data', 'meta' => ['currentPage', 'perPage', 'total', 'lastPage'], 'links']);

    expect(collect($response->json('data'))->pluck('id'))->toContain($this->damage->id, $this->late->id)->not->toContain($this->qoqa->id);
});

it('exposes no internal field', function (): void {
    $row = collect(listClaims()->json('data'))->firstWhere('id', $this->damage->id);

    expect($row)->not->toHaveKeys(['cost', 'decision', 'cause', 'followUp', 'result', 'responsibleUserId', 'customerId', 'organizationId', 'createdBy'])
        ->and(json_encode($row, JSON_UNESCAPED_UNICODE))->not->toContain('Avoir interne');
});

it('searches the title and the description only', function (): void {
    expect(listClaims('?search=retard')->json('data.0.id'))->toBe($this->late->id)
        ->and(listClaims('?search=Avoir')->json('data'))->toBeEmpty();
});

it('filters by type, state, order and dates', function (): void {
    expect(listClaims('?claimType=delay')->json('meta.total'))->toBe(1)
        ->and(listClaims('?state=open')->json('data.0.id'))->toBe($this->damage->id)
        ->and(listClaims('?state=closed')->json('data.0.id'))->toBe($this->late->id)
        ->and(listClaims('?orderId='.$this->damage->order_id)->json('meta.total'))->toBe(2)
        ->and(listClaims('?createdFrom='.now()->subDay()->toDateString())->json('meta.total'))->toBe(1)
        ->and(listClaims('?status[]=open')->json('meta.total'))->toBe(2);
});

it('sorts and bounds the page size', function (): void {
    expect(listClaims('?sort=title&direction=asc')->json('data.0.title'))->toBe('Carton écrasé')
        ->and(listClaims('?perPage=1')->json('meta.lastPage'))->toBe(2);

    listClaims('?perPage=500')->assertUnprocessable()->assertJsonValidationErrors('perPage');
    listClaims('?sort=cost')->assertUnprocessable()->assertJsonValidationErrors('sort');
});

it('never lets the customer be chosen by the request', function (): void {
    listClaims('?customerId='.$this->qoqa->customer_id)->assertUnprocessable()->assertJsonValidationErrors('customerId');
});

it('requires the view permission', function (): void {
    CustomerPortalTracking::giveRole('viewer@ikea.test', CustomerPortalRoles::STOCK_MANAGER);

    listClaims('', 'viewer@ikea.test')->assertForbidden();
});
