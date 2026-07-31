<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed();
});

describe('login', function (): void {
    it('allows a valid user to login', function (): void {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'user@example.com')
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'firstName', 'lastName', 'email'],
                    'token',
                ],
                'meta',
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    });

    it('rejects an invalid password', function (): void {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
    });

    it('rejects a suspended user', function (): void {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('password'),
            'status' => UserStatus::SUSPENDED,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('audits a failed login without leaking the password', function (): void {
        $user = authUser();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'mauvais-mot-de-passe'])
            ->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login_failed',
            'user_id' => $user->id,
            'entity_type' => 'user',
        ]);

        $log = AuditLog::where('action', 'login_failed')->firstOrFail();
        expect($log->new_values)->toBe(['reason' => 'invalid_credentials']);
    });

    it('audits a login attempt on a suspended account', function (): void {
        $user = authUser();
        $user->update(['status' => UserStatus::SUSPENDED]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', ['action' => 'login_failed', 'user_id' => $user->id]);
    });

    it('does not audit a login attempt on an unknown email', function (): void {
        $this->postJson('/api/v1/auth/login', ['email' => 'inconnu@tricolis.dev', 'password' => 'peu importe'])
            ->assertStatus(422);

        $this->assertDatabaseCount('audit_logs', 0);
    });
});

describe('sessions', function (): void {
    it('logs out the current session only', function (): void {
        $user = authUser();
        $current = $user->createToken('poste-1');
        $other = $user->createToken('poste-2');

        $this->withHeader('Authorization', 'Bearer '.$current->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'logout', 'user_id' => $user->id]);
    });

    it('logs out every session at once', function (): void {
        $user = authUser();
        $user->createToken('poste-1');
        $token = $user->createToken('poste-2');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/auth/logout-all')
            ->assertNoContent();

        expect($user->tokens()->count())->toBe(0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'logout_all', 'user_id' => $user->id]);
    });

    it('rejects logout without authentication', function (): void {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    });

    it('returns the authenticated user', function (): void {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    });
});
