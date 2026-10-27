<?php

use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;

/**
 * En-têtes HTTP de l'API (Phase 7, §33) : posés sur toute réponse, y compris
 * une erreur, sans écraser ceux qu'un contrôleur a choisis.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
});

it('pose les en-têtes de sécurité sur le portail, le lien public et les erreurs', function (string $uri, bool $authenticated): void {
    $response = ($authenticated ? $this->withHeaders(CustomerPortal::headers('admin@ikea.test')) : $this)->getJson($uri);

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse();
})->with([
    'portail' => ['/api/v1/customer/context', true],
    'refus' => ['/api/v1/customer/orders', false],
    'lien public inconnu' => ['/api/v1/public/tracking/inconnu', false],
]);

it('n’émet HSTS que sur une requête en HTTPS', function (): void {
    $this->withServerVariables(['HTTPS' => 'on'])
        ->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson('https://localhost/api/v1/customer/context')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('n’accepte aucune origine non déclarée en CORS', function (): void {
    $response = $this->withHeaders(['Origin' => 'https://evil.example.com', 'Access-Control-Request-Method' => 'GET'])
        ->options('/api/v1/customer/context');

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('*')
        ->and($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example.com')
        ->and(config('cors.supports_credentials'))->toBeFalse();
});
