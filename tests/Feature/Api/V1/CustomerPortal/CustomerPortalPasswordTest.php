<?php

use App\Modules\CustomerPortal\Mail\CustomerPortalMail;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CustomerPortal;

/**
 * Mot de passe oublié et réinitialisation, côté portail client.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    Mail::fake();
});

it('sends a reset link that leads to the customer portal', function (): void {
    $this->postJson('/api/v1/customer/auth/forgot-password', ['email' => 'viewer@ikea.test'])->assertOk();

    Mail::assertSent(CustomerPortalMail::class, static fn (CustomerPortalMail $mail): bool => $mail->hasTo('viewer@ikea.test')
        && $mail->kind === CustomerPortalMail::PASSWORD_RESET
        && str_starts_with($mail->actionUrl, config('customer_portal.url').'/reset-password?token='));
});

it('answers the same way for an unknown or internal-only address, and sends nothing', function (): void {
    $known = $this->postJson('/api/v1/customer/auth/forgot-password', ['email' => 'viewer@ikea.test'])->json('data.message');

    $this->postJson('/api/v1/customer/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertOk()->assertJsonPath('data.message', $known);
    $this->postJson('/api/v1/customer/auth/forgot-password', ['email' => 'admin@tricolis.dev'])
        ->assertOk()->assertJsonPath('data.message', $known);

    Mail::assertSent(CustomerPortalMail::class, 1);
});

it('resets the password with the mailed token, then allows login with it', function (): void {
    $this->postJson('/api/v1/customer/auth/forgot-password', ['email' => 'viewer@ikea.test']);
    $token = CustomerPortal::mailedToken('viewer@ikea.test', CustomerPortalMail::PASSWORD_RESET);
    $oldSession = CustomerPortal::token('viewer@ikea.test');

    $this->postJson('/api/v1/customer/auth/reset-password', [
        'token' => $token,
        'email' => 'viewer@ikea.test',
        'password' => 'Nouveau-Motdepasse-2026!',
        'password_confirmation' => 'Nouveau-Motdepasse-2026!',
    ])->assertOk();

    $this->postJson('/api/v1/customer/auth/login', ['email' => 'viewer@ikea.test', 'password' => 'Nouveau-Motdepasse-2026!'])->assertOk();
    $this->withHeaders(CustomerPortal::bearer($oldSession))->getJson('/api/v1/customer/auth/me')->assertUnauthorized();
});

it('rejects an invalid reset token', function (): void {
    $this->postJson('/api/v1/customer/auth/reset-password', [
        'token' => 'not-a-token',
        'email' => 'viewer@ikea.test',
        'password' => 'Nouveau-Motdepasse-2026!',
        'password_confirmation' => 'Nouveau-Motdepasse-2026!',
    ])->assertUnprocessable();
});
