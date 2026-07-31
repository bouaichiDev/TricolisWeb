<?php

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    config()->set('log-viewer.enabled', true);
    config()->set('log-viewer.allowed_emails', ['technical@example.com']);
});

it('requires authentication to view application logs', function (): void {
    $this->get('/log-viewer')->assertUnauthorized();
});

it('allows only an explicitly authorized technical administrator', function (): void {
    User::factory()->create([
        'email' => 'technical@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $this->withHeader(
        'Authorization',
        'Basic '.base64_encode('technical@example.com:secret-password'),
    )->get('/log-viewer')->assertOk();
});

it('rejects an authenticated user outside the technical allowlist', function (): void {
    User::factory()->create([
        'email' => 'transporter@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $this->withHeader(
        'Authorization',
        'Basic '.base64_encode('transporter@example.com:secret-password'),
    )->get('/log-viewer')->assertForbidden();
});
