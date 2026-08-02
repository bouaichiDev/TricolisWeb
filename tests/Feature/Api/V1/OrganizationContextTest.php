<?php

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
});

it('requires an organization header on business routes', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/customers')
        ->assertForbidden()
        ->assertJsonPath('message', 'L’en-tête X-Organization-Id est requis.');
});

it('rejects a malformed organization identifier', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', 'not-an-ulid')
        ->getJson('/api/v1/customers')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'L\'identifiant d\'organisation est invalide.');
});

it('keeps organization listing accessible without an active organization', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/organizations')
        ->assertOk();
});
