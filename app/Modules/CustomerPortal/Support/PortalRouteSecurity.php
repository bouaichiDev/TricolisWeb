<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Support;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Lit, route par route, ce qui protège le périmètre externe : authentification,
 * contexte client, permission, isolation, débit.
 *
 * Sert la matrice documentaire comme le test de sécurité : les deux décrivent
 * le routeur réel. Une route sensible sans permission n'est admise que si
 * elle figure dans `SESSION_BOUND` — son contrôle est alors documenté.
 */
final readonly class PortalRouteSecurity
{
    /**
     * Routes authentifiées sans permission de middleware, et pourquoi.
     *
     * @var array<string, string>
     */
    public const array SESSION_BOUND = [
        'api/v1/customer/auth/me' => 'le compte connecté lui-même',
        'api/v1/customer/auth/logout' => 'révoque le jeton de la requête',
        'api/v1/customer/context' => 'le contexte de la session',
        'api/v1/customer/permissions' => 'les permissions de la session',
        'api/v1/customer/profile' => 'le profil du compte connecté',
        'api/v1/customer/dashboard' => 'chaque widget exige sa permission (CustomerDashboardComposer)',
        'api/v1/customer/documents/{document}' => 'CustomerDocumentAccess : permission du document selon son rattachement',
        'api/v1/customer/documents/{document}/download' => 'CustomerDocumentAccess : permission du document selon son rattachement',
    ];

    /** Mécanisme d'isolation, par préfixe d'URI (le plus long l'emporte). */
    private const array ISOLATION = [
        'api/v1/customer/orders' => 'ResolvesCustomerOrder (client + organisation)',
        'api/v1/customer/calendar' => 'CustomerCalendarQuery (client + organisation)',
        'api/v1/customer/claims' => 'CustomerClaimListQuery / réclamation du client',
        'api/v1/customer/documents' => 'CustomerDocumentAccess',
        'api/v1/customer/proofs-of-delivery' => 'CustomerProofsOfDelivery',
        'api/v1/customer/stock' => 'CustomerStockScope',
        'api/v1/customer/integrations' => 'CustomerIntegrationScope',
        'api/v1/customer/users' => 'CustomerUserAccess (même client)',
        'api/v1/customer/roles' => 'rôles portail de l’organisation',
        'api/v1/customer/catalog-items' => 'catalogue du client',
        'api/v1/customer/catalog' => 'CustomerCatalogScope (catalogues du client + organisation)',
        'api/v1/customer/order-form-options' => 'options du client',
        'api/v1/customer/dashboard' => 'CustomerDashboardComposer',
        'api/v1/customer' => 'session (compte connecté)',
        'api/v1/customer/auth' => 'avant authentification : identifiants, jeton d’invitation ou de réinitialisation',
        'api/v1/customer/auth/me' => 'session (compte connecté)',
        'api/v1/customer/auth/logout' => 'jeton de la requête',
        'api/v1/public/tracking' => 'jeton public aléatoire (PublicTrackingLinks)',
        'api/v1/client' => 'client de la clé API (CustomerApiContext)',
    ];

    public function __construct(private Router $router) {}

    /**
     * @return list<array{method: string, uri: string, auth: string, context: string, permission: list<string>, isolation: string, throttle: list<string>, gap: string|null}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (! $this->isExternal($uri)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $rows[] = $this->row($route, $uri, array_map('strval', $middleware));
        }

        usort($rows, static fn (array $a, array $b): int => [$a['uri'], $a['method']] <=> [$b['uri'], $b['method']]);

        return $rows;
    }

    public function isExternal(string $uri): bool
    {
        return str_starts_with($uri, 'api/v1/customer/') || $uri === 'api/v1/customer'
            || str_starts_with($uri, 'api/v1/public/') || str_starts_with($uri, 'api/v1/client/');
    }

    /**
     * @param  list<string>  $middleware
     * @return array{method: string, uri: string, auth: string, context: string, permission: list<string>, isolation: string, throttle: list<string>, gap: string|null}
     */
    private function row(Route $route, string $uri, array $middleware): array
    {
        $sanctum = in_array('auth:sanctum', $middleware, true);
        $apiKey = $this->matching($middleware, 'customer-api') !== [];
        $context = in_array('customer.portal', $middleware, true);
        $permissions = [];

        foreach ($this->matching($middleware, 'customer.permission:') as $entry) {
            $permissions = [...$permissions, ...explode(',', substr($entry, strlen('customer.permission:')))];
        }

        foreach ($this->matching($middleware, 'customer-api:') as $entry) {
            $permissions[] = substr($entry, strlen('customer-api:'));
        }

        return [
            'method' => implode('|', array_diff($route->methods(), ['HEAD'])),
            'uri' => $uri,
            'auth' => $sanctum ? 'Sanctum (jeton portail)' : ($apiKey ? 'Clé API' : 'Aucune (public)'),
            'context' => $context ? 'customer.portal' : '—',
            'permission' => array_values(array_unique($permissions)),
            'isolation' => $this->isolationOf($uri),
            'throttle' => array_map(static fn (string $entry): string => substr($entry, strlen('throttle:')), $this->matching($middleware, 'throttle:')),
            'gap' => $this->gapOf($uri, $sanctum, $apiKey, $context, $permissions, $middleware),
        ];
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $middleware
     */
    private function gapOf(string $uri, bool $sanctum, bool $apiKey, bool $context, array $permissions, array $middleware): ?string
    {
        $public = str_starts_with($uri, 'api/v1/customer/auth/') && ! $sanctum;

        if ($public || str_starts_with($uri, 'api/v1/public/')) {
            return $this->matching($middleware, 'throttle:') === [] && ! str_ends_with($uri, '/login')
                ? 'route publique sans limite de débit'
                : null;
        }

        if ($apiKey) {
            return null;
        }

        if (! $sanctum || ! $context) {
            return 'authentification ou contexte client absent';
        }

        return $permissions === [] && ! array_key_exists($uri, self::SESSION_BOUND)
            ? 'aucune permission ni contrôle documenté'
            : null;
    }

    private function isolationOf(string $uri): string
    {
        $best = '';

        foreach (array_keys(self::ISOLATION) as $prefix) {
            if (str_starts_with($uri, $prefix) && strlen($prefix) > strlen($best)) {
                $best = $prefix;
            }
        }

        return self::ISOLATION[$best] ?? '—';
    }

    /**
     * @param  list<string>  $middleware
     * @return list<string>
     */
    private function matching(array $middleware, string $prefix): array
    {
        return array_values(array_filter($middleware, static fn (string $entry): bool => str_starts_with($entry, $prefix)));
    }
}
