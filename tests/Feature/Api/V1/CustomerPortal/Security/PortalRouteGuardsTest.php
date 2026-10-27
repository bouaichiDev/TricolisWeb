<?php

use App\Http\Middleware\EnsureCustomerPortalContext;
use App\Modules\CustomerPortal\Support\PortalRouteSecurity;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\PortalRoutes;

/**
 * Audit automatisé des routes du portail (Phase 7, §49) : chaque route
 * externe a une autorisation explicite, et le serveur le prouve route par
 * route — sans jeton, avec un jeton interne, avec un membre sans permission.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->routes = PortalRoutes::authenticated();
});

it('inventorie au moins toutes les routes connues, chacune autorisée explicitement', function (): void {
    $rows = app(PortalRouteSecurity::class)->rows();
    $gaps = array_filter($rows, static fn (array $row): bool => $row['gap'] !== null);

    expect(count($this->routes))->toBeGreaterThan(70)
        ->and(array_map(static fn (array $row): string => "{$row['method']} {$row['uri']} : {$row['gap']}", $gaps))->toBe([]);
});

it('refuse toute route du portail sans jeton (401)', function (): void {
    $answers = [];

    foreach ($this->routes as $route) {
        $status = $this->json($route['method'], PortalRoutes::fill($route['uri']))->status();

        if ($status !== 401) {
            $answers[] = "{$route['method']} {$route['uri']} → {$status}";
        }
    }

    expect($answers)->toBe([]);
});

it('refuse toute route du portail à un jeton du back-office (403)', function (): void {
    $headers = CustomerPortal::bearer(CustomerPortal::internalToken(authUser()));
    $answers = [];

    foreach ($this->routes as $route) {
        $response = $this->withHeaders($headers)->json($route['method'], PortalRoutes::fill($route['uri']));

        if ($response->status() !== 403 || $response->json('code') !== EnsureCustomerPortalContext::REVOKED) {
            $answers[] = "{$route['method']} {$route['uri']} → {$response->status()}";
        }
    }

    expect($answers)->toBe([]);
});

it('refuse chaque route à permission à un membre qui n’en a aucune (403)', function (): void {
    $headers = CustomerPortal::headers(PortalRoutes::memberWithoutRoles('ikea'));
    $guarded = array_filter($this->routes, static fn (array $route): bool => $route['permission'] !== []);
    $answers = [];

    foreach ($guarded as $route) {
        $status = $this->withHeaders($headers)->json($route['method'], PortalRoutes::fill($route['uri']))->status();

        if ($status !== 403) {
            $answers[] = "{$route['method']} {$route['uri']} → {$status}";
        }
    }

    expect(count($guarded))->toBeGreaterThan(60)->and($answers)->toBe([]);
});

it('ne donne au membre sans permission que sa session, rien de métier', function (): void {
    $headers = CustomerPortal::headers(PortalRoutes::memberWithoutRoles('ikea'));

    $this->withHeaders($headers)->getJson('/api/v1/customer/permissions')->assertOk()->assertJsonPath('data.permissions', []);
    $this->withHeaders($headers)->getJson('/api/v1/customer/dashboard')->assertOk()->assertJsonPath('data.widgets', []);
});

it('refuse un jeton du portail sur les routes du back-office', function (string $uri): void {
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test') + ['X-Organization-Id' => authOrganization()->id])
        ->getJson($uri)
        ->assertForbidden();
})->with([
    '/api/v1/orders', '/api/v1/customers', '/api/v1/users', '/api/v1/roles', '/api/v1/permissions',
    '/api/v1/audit-logs', '/api/v1/customer-api-configurations', '/api/v1/export-jobs', '/api/v1/invoices',
]);

it('n’expose aucune route d’écriture ou de lecture de l’audit dans le portail', function (): void {
    $audit = array_filter($this->routes, static fn (array $route): bool => str_contains($route['uri'], 'audit'));

    expect($audit)->toBe([]);
});
