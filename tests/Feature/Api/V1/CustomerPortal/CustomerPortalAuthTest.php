<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Models\CustomerUserRole;
use App\Modules\CustomerPortal\Support\CustomerPortalToken;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Shared\Enums\UserStatus;
use Database\Seeders\CustomerPortalDemoSeeder;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\CustomerPortal;

/**
 * Authentification du portail client : /api/v1/customer/auth/*.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
});

function customerLogin(string $email, string $password = CustomerPortal::PASSWORD, array $extra = [])
{
    return test()->postJson('/api/v1/customer/auth/login', ['email' => $email, 'password' => $password, ...$extra]);
}

it('logs a customer admin in and returns the customer context', function (): void {
    $response = customerLogin('admin@ikea.test')->assertOk()
        ->assertJsonPath('data.context.customer.code', 'ikea')
        ->assertJsonPath('data.context.user.email', 'admin@ikea.test')
        ->assertJsonPath('data.context.membership.isOwner', true);

    expect($response->json('data.context.permissions'))->toContain('customer.users.invite');

    $token = PersonalAccessToken::findToken($response->json('data.token'));
    expect($token->abilities)->toBe(CustomerPortalToken::abilitiesFor(CustomerPortal::customer('ikea')->id));

    expect(AuditLog::where('action', 'customer_portal.login')->where('user_id', $token->tokenable_id)->exists())->toBeTrue();
});

it('rejects a wrong password', function (): void {
    customerLogin('admin@ikea.test', 'wrong-password')->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('rejects a globally disabled user', function (): void {
    CustomerPortal::member('viewer@ikea.test')->user->update(['status' => UserStatus::DISABLED]);

    customerLogin('viewer@ikea.test')->assertUnprocessable();
});

it('rejects a disabled portal membership', function (): void {
    CustomerPortal::member('viewer@ikea.test')->update(['status' => UserStatus::DISABLED]);

    customerLogin('viewer@ikea.test')->assertUnprocessable();
});

it('rejects a member of a disabled customer', function (): void {
    CustomerPortal::customer('ikea')->update(['status' => CustomerStatus::INACTIVE]);

    customerLogin('admin@ikea.test')->assertUnprocessable();
});

it('rejects an internal user who has no portal membership', function (): void {
    customerLogin('admin@tricolis.dev')->assertUnprocessable();
});

it('logs out by revoking only the current token', function (): void {
    $token = customerLogin('admin@ikea.test')->json('data.token');

    $this->withHeaders(CustomerPortal::bearer($token))->postJson('/api/v1/customer/auth/logout')->assertNoContent();

    expect(PersonalAccessToken::findToken($token))->toBeNull();
    $this->withHeaders(CustomerPortal::bearer($token))->getJson('/api/v1/customer/auth/me')->assertUnauthorized();
});

it('asks a member of several customers to choose, then honours the choice', function (): void {
    $member = CustomerPortal::member('orders@ikea.test');
    $qoqa = CustomerPortal::customer('qoqa');
    $membership = CustomerUser::create([
        'customer_id' => $qoqa->id, 'user_id' => $member->user_id, 'status' => UserStatus::ACTIVE, 'activated_at' => now(),
    ]);
    CustomerUserRole::create([
        'customer_user_id' => $membership->id,
        'role_id' => Role::where('organization_id', $qoqa->organization_id)->where('code', 'CUSTOMER_VIEWER')->value('id'),
    ]);

    customerLogin('orders@ikea.test')->assertStatus(409)
        ->assertJsonPath('code', 'customer_selection_required')
        ->assertJsonCount(2, 'customers');

    customerLogin('orders@ikea.test', extra: ['customerId' => $qoqa->id])->assertOk()
        ->assertJsonPath('data.context.customer.code', 'qoqa');
});

it('refuses a customerId the account does not belong to', function (): void {
    customerLogin('admin@ikea.test', extra: ['customerId' => CustomerPortal::customer('qoqa')->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customerId');
});

it('never exposes a customer that is not active in the selection', function (): void {
    Customer::where('code', 'qoqa')->update(['status' => CustomerStatus::BLOCKED->value]);

    customerLogin('admin@qoqa.test')->assertUnprocessable();
});
