<?php

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

it('rejects a disabled user login', function (): void {
    User::factory()->create(['email' => 'disabled@example.test', 'password' => Hash::make('password'), 'status' => UserStatus::DISABLED]);
    $this->postJson('/api/v1/auth/login', ['email' => 'disabled@example.test', 'password' => 'password'])->assertUnprocessable();
});

it('returns organizations roles permissions and agencies at login', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'admin@tricolis.dev', 'password' => 'password'])->assertOk()->assertJsonStructure(['data' => ['user' => ['organizations' => [['roles', 'permissions', 'agencies']]]]]);
});

it('revokes one owned session', function (): void {
    $token = $this->user->createToken('test-session')->accessToken;
    $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v1/auth/sessions/$token->id")->assertNoContent();
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
});

it('limits pagination and rejects forbidden sorting', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->getJson('/api/v1/customers?perPage=101')->assertUnprocessable()->assertJsonValidationErrors('perPage');
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->getJson('/api/v1/customers?sort=password')->assertUnprocessable()->assertJsonValidationErrors('sort');
});

it('rejects a malformed ulid', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->postJson('/api/v1/orders', ['customerId' => str_repeat('x', 26), 'agencyId' => str_repeat('x', 26), 'orderNumber' => 'BAD', 'orderDate' => now()->toISOString(), 'lines' => [], 'services' => []])->assertUnprocessable()->assertJsonValidationErrors(['customerId', 'agencyId']);
});

it('forbids reading customers without the backend permission', function (): void {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    OrganizationUser::create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'is_owner' => false, 'is_primary' => true, 'status' => UserStatus::ACTIVE, 'joined_at' => now()]);
    $this->actingAs($user, 'sanctum')->withHeaders($this->headers)->getJson('/api/v1/customers')->assertForbidden();
});
