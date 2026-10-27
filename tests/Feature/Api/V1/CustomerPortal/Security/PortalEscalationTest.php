<?php

use App\Modules\CustomerPortal\Support\CustomerPortalRoles;
use App\Modules\Identity\Models\Role;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Enums\RoleScope;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Escalade de privilèges (Phase 7, §9-§10, §20) : ce que le serveur impose ne
 * se choisit pas dans un payload, et un administrateur client ne sort jamais
 * des rôles du portail de son transporteur.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->admin = CustomerPortal::headers('admin@ikea.test');
    $this->viewer = CustomerPortal::member('viewer@ikea.test');
});

it('refuse une commande qui fixe elle-même client, statut, prix ou coûts', function (string $field, mixed $value): void {
    $payload = CustomerPortalOrders::payload();
    data_set($payload, $field, $value === 'QOQA' ? CustomerPortal::customer('qoqa')->id : $value);
    $count = Order::count();

    $this->withHeaders($this->admin)->postJson('/api/v1/customer/orders', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Order::count())->toBe($count);
})->with([
    'client' => ['customerId', 'QOQA'],
    'organisation' => ['organizationId', '01JZZZZZZZZZZZZZZZZZZZZZZZ'],
    'statut' => ['status', 'completed'],
    'origine' => ['source', 'internal'],
    'remarque interne' => ['internalRemark', 'x'],
    'coût fournisseur' => ['services.0.providerUnitCost', 1],
    'prix client' => ['services.0.customerUnitPrice', 0],
    'prix d’achat' => ['lines.0.purchasePrice', 0],
]);

it('refuse une invitation qui choisit son client, son statut ou ses droits', function (string $field, mixed $value): void {
    $value = $value === 'QOQA' ? CustomerPortal::customer('qoqa')->id : $value;

    $this->withHeaders($this->admin)->postJson('/api/v1/customer/users/invite', ['email' => 'nouveau@ikea.test', $field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'client' => ['customerId', 'QOQA'],
    'administrateur' => ['isAdmin', true],
    'propriétaire' => ['isOwner', true],
    'statut' => ['status', 'active'],
    'permissions' => ['permissions', ['customer.users.invite']],
    'mot de passe' => ['password', 'secret'],
]);

it('refuse qu’un membre change son statut, son client ou ses droits par sa fiche', function (string $field, mixed $value): void {
    $this->withHeaders($this->admin)->putJson("/api/v1/customer/users/{$this->viewer->id}", [$field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'statut' => ['status', 'active'],
    'administrateur' => ['isAdmin', true],
    'courriel' => ['email', 'pirate@example.test'],
    'rôle' => ['roleId', '01JZZZZZZZZZZZZZZZZZZZZZZZ'],
]);

it('refuse un rôle interne, un rôle d’une autre organisation, ou des permissions directes', function (): void {
    $internal = Role::where('scope', RoleScope::ORGANIZATION->value)->firstOrFail();
    $foreign = Role::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
        'code' => CustomerPortalRoles::ADMIN,
        'scope' => RoleScope::CUSTOMER->value,
    ]);
    $uri = "/api/v1/customer/users/{$this->viewer->id}/roles";

    $this->withHeaders($this->admin)->putJson($uri, ['roleIds' => [$internal->id]])->assertUnprocessable();
    $this->withHeaders($this->admin)->putJson($uri, ['roleIds' => [$foreign->id]])->assertUnprocessable();
    $this->withHeaders($this->admin)->putJson($uri, ['roleIds' => [], 'permissions' => ['users.delete']])->assertUnprocessable();

    expect($this->viewer->fresh('roles')->roles->pluck('code')->all())->toBe([CustomerPortalRoles::VIEWER]);
});

it('ne laisse pas un gestionnaire se donner à lui-même le rôle d’administrateur', function (): void {
    $manager = CustomerPortal::member('orders@ikea.test');
    $admin = Role::where('code', CustomerPortalRoles::ADMIN)->where('organization_id', CustomerPortal::customer('ikea')->organization_id)->firstOrFail();

    $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))
        ->putJson("/api/v1/customer/users/{$manager->id}/roles", ['roleIds' => [$admin->id]])
        ->assertForbidden();

    expect($manager->fresh('roles')->roles->pluck('code')->all())->toBe([CustomerPortalRoles::ORDER_MANAGER]);
});
