<?php

use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Route;
use Tests\Support\CustomerPortal;
use Tests\Support\PortalRoutes;

/**
 * Erreurs du périmètre externe (Phase 8, §25) : ni classe interne, ni SQL,
 * ni trace — même avec `APP_DEBUG` activé —, les refus prévus intacts.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    config(['app.debug' => true]);
});

it('ne nomme jamais la classe interne d’une ressource introuvable', function (string $uri): void {
    $response = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson($uri)->assertNotFound();

    expect($response->json('message'))->toBe('Ressource introuvable.')
        ->and($response->getContent())->not->toContain('App\\\\Modules')
        ->and($response->getContent())->not->toContain('No query results');
})->with([
    '/api/v1/customer/orders/'.PortalRoutes::NOWHERE,
    '/api/v1/customer/catalog/items/'.PortalRoutes::NOWHERE,
    '/api/v1/customer/claims/'.PortalRoutes::NOWHERE,
]);

it('rend une erreur imprévue sans SQL, sans hôte, sans trace, même en débogage', function (): void {
    Route::middleware('api')->get('api/v1/customer/__phase8-boom', static fn () => throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused to 10.0.0.5 in /var/www/app.php'));

    $response = $this->getJson('/api/v1/customer/__phase8-boom')->assertStatus(500);

    expect($response->json('message'))->toStartWith('Une erreur interne est survenue')
        ->and($response->getContent())->not->toContain('SQLSTATE')
        ->and($response->getContent())->not->toContain('10.0.0.5')
        ->and($response->getContent())->not->toContain('/var/www')
        ->and($response->json())->not->toHaveKey('trace');
});

it('garde le message des refus prévus', function (): void {
    $this->getJson('/api/v1/customer/context')->assertUnauthorized();
    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson('/api/v1/customer/users')
        ->assertForbidden()
        ->assertJsonPath('message', 'Vous n’avez pas la permission requise pour cette action.');
});

it('ne change rien aux erreurs du back-office', function (): void {
    Route::middleware('api')->get('api/v1/__phase8-internal-boom', static fn () => throw new RuntimeException('internal detail visible in debug'));

    expect($this->getJson('/api/v1/__phase8-internal-boom')->getContent())->toContain('internal detail visible in debug');
});
