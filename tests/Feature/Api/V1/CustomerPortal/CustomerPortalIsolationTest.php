<?php

use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Customers\Models\Customer;
use App\Shared\Enums\UserStatus;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;

/**
 * Test critique : un utilisateur IKEA n'atteint jamais QOQA, quel que soit
 * l'identifiant qu'il met dans l'URL — et les jetons portail et interne ne
 * se croisent pas.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->qoqaAdmin = CustomerPortal::member('admin@qoqa.test');
    $this->ikea = CustomerPortal::headers('admin@ikea.test');
});

it('answers 404 when an IKEA admin reads a QOQA member', function (): void {
    $this->withHeaders($this->ikea)->getJson("/api/v1/customer/users/{$this->qoqaAdmin->id}")->assertNotFound();
});

it('answers 404 on every write aimed at a QOQA member', function (string $method, string $suffix, array $payload): void {
    $this->withHeaders($this->ikea)->json($method, "/api/v1/customer/users/{$this->qoqaAdmin->id}{$suffix}", $payload)->assertNotFound();

    expect($this->qoqaAdmin->fresh()->status)->toBe(UserStatus::ACTIVE);
})->with([
    'update' => ['PUT', '', ['firstName' => 'Pirate']],
    'disable' => ['POST', '/disable', []],
    'enable' => ['POST', '/enable', []],
    'resend' => ['POST', '/resend-invitation', []],
    'roles' => ['PUT', '/roles', ['roleIds' => []]],
]);

it('lists only the members of the current customer', function (): void {
    $emails = collect($this->withHeaders($this->ikea)->getJson('/api/v1/customer/users?perPage=100')->assertOk()->json('data'))
        ->pluck('user.email');

    expect($emails)->toContain('admin@ikea.test', 'viewer@ikea.test')
        ->not->toContain('admin@qoqa.test');
});

it('never lets a request choose its customer', function (): void {
    $qoqaId = CustomerPortal::customer('qoqa')->id;

    $this->withHeaders([...$this->ikea, 'X-Customer-Id' => $qoqaId, 'X-Organization-Id' => $qoqaId])
        ->getJson("/api/v1/customer/context?customerId={$qoqaId}")
        ->assertOk()
        ->assertJsonPath('data.customer.code', 'ikea');
});

it('keeps portal tokens out of the internal API', function (): void {
    $member = CustomerPortal::member('admin@ikea.test');

    $this->withHeaders([...$this->ikea, 'X-Organization-Id' => $member->customer->organization_id])
        ->getJson('/api/v1/auth/me')
        ->assertForbidden();
    $this->withHeaders($this->ikea)->getJson('/api/v1/menu')->assertForbidden();
});

it('keeps internal tokens out of the portal', function (): void {
    $token = CustomerPortal::internalToken(authUser());

    $this->withHeaders(CustomerPortal::bearer($token))->getJson('/api/v1/customer/context')
        ->assertForbidden()
        ->assertJsonPath('code', 'customer_access_revoked');
});

it('cuts access on the next request once the customer is disabled', function (): void {
    $this->withHeaders($this->ikea)->getJson('/api/v1/customer/context')->assertOk();

    CustomerPortal::customer('ikea')->update(['status' => CustomerStatus::BLOCKED]);

    $this->withHeaders($this->ikea)->getJson('/api/v1/customer/context')
        ->assertForbidden()
        ->assertJsonPath('code', 'customer_access_revoked');
});

it('cuts access on the next request once the membership is disabled', function (): void {
    $headers = CustomerPortal::headers('viewer@ikea.test');
    CustomerPortal::member('viewer@ikea.test')->update(['status' => UserStatus::SUSPENDED]);

    $this->withHeaders($headers)->getJson('/api/v1/customer/context')->assertForbidden();
});

it('lets the carrier invite only into its own customers', function (): void {
    $foreign = Customer::factory()->create();

    $this->actingAs(authUser(), 'sanctum')
        ->withHeaders(['X-Organization-Id' => authOrganization()->id])
        ->postJson("/api/v1/customers/{$foreign->id}/portal-users/invite", ['email' => 'intrus@example.com'])
        ->assertNotFound();
});
