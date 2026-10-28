<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Support;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Lit, route par route, ce qui protège le portail fournisseur :
 * authentification, contexte fournisseur, permission, isolation, débit.
 *
 * Sert la matrice documentaire (`provider-portal:route-matrix`) comme le test
 * de sécurité : les deux décrivent le routeur réel, une route ajoutée demain
 * y entre d'office. Une route authentifiée sans permission n'est admise que si
 * elle figure dans `SESSION_BOUND` ; une écriture sans limite de débit, jamais.
 */
final readonly class ProviderRouteSecurity
{
    public const string PREFIX = 'api/v1/provider';

    /**
     * Routes authentifiées sans permission de middleware, et pourquoi.
     *
     * @var array<string, string>
     */
    public const array SESSION_BOUND = [
        'api/v1/provider/auth/me' => 'le compte connecté lui-même',
        'api/v1/provider/auth/logout' => 'révoque le jeton de la requête',
        'api/v1/provider/navigation' => 'menu filtré par les permissions de la session',
        'api/v1/provider/profile' => 'PUT : le compte connecté (provider.portal.access revérifié par le contexte)',
        'api/v1/provider/profile/password' => 'le compte connecté, mot de passe actuel exigé',
    ];

    /** Mécanisme d'isolation, par préfixe d'URI (le plus long l'emporte). */
    private const array ISOLATION = [
        'api/v1/provider' => 'session (compte et fournisseur du jeton)',
        'api/v1/provider/auth' => 'avant authentification : identifiants, jeton d’invitation ou de réinitialisation',
        'api/v1/provider/auth/me' => 'session (compte connecté)',
        'api/v1/provider/auth/logout' => 'jeton de la requête',
        'api/v1/provider/dashboard' => 'ProviderDashboardComposer (fournisseur + organisation)',
        'api/v1/provider/tours' => 'ProviderTourScope (fournisseur + organisation, hors brouillon)',
        'api/v1/provider/documents' => 'ProviderDocumentAccess (tournée visible, service acquis)',
        'api/v1/provider/drivers' => 'ProviderResourceScope (fournisseur + organisation)',
        'api/v1/provider/vehicles' => 'ProviderResourceScope (fournisseur + organisation)',
        'api/v1/provider/vehicle-types' => 'référentiel de l’organisation',
        'api/v1/provider/communications' => 'ProviderCommunications (adresse du membre + organisation)',
        'api/v1/provider/settlements' => 'ProviderSettlementScope (fournisseur + organisation, clôturés)',
    ];

    public function __construct(private Router $router) {}

    /**
     * @return list<array{method: string, uri: string, auth: string, context: string, permission: list<string>, isolation: string, throttle: list<string>, gap: string|null}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($route->uri() === self::PREFIX || str_starts_with($route->uri(), self::PREFIX.'/')) {
                $rows[] = $this->row($route, array_map('strval', $route->gatherMiddleware()));
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$a['uri'], $a['method']] <=> [$b['uri'], $b['method']]);

        return $rows;
    }

    /**
     * @param  list<string>  $middleware
     * @return array{method: string, uri: string, auth: string, context: string, permission: list<string>, isolation: string, throttle: list<string>, gap: string|null}
     */
    private function row(Route $route, array $middleware): array
    {
        $uri = $route->uri();
        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        $sanctum = in_array('auth:sanctum', $middleware, true);
        $context = in_array('provider.portal', $middleware, true);
        $permissions = [];

        foreach ($this->matching($middleware, 'provider.permission:') as $entry) {
            $permissions = [...$permissions, ...explode(',', substr($entry, strlen('provider.permission:')))];
        }

        $throttle = array_map(static fn (string $entry): string => substr($entry, strlen('throttle:')), $this->matching($middleware, 'throttle:'));

        return [
            'method' => implode('|', $methods),
            'uri' => $uri,
            'auth' => $sanctum ? 'Sanctum (jeton portail fournisseur)' : 'Aucune (public)',
            'context' => $context ? 'provider.portal' : '—',
            'permission' => array_values(array_unique($permissions)),
            'isolation' => $this->isolationOf($uri),
            'throttle' => $throttle,
            'gap' => $this->gapOf($uri, $methods, $sanctum, $context, $permissions, $throttle),
        ];
    }

    /**
     * @param  list<string>  $methods
     * @param  list<string>  $permissions
     * @param  list<string>  $throttle
     */
    private function gapOf(string $uri, array $methods, bool $sanctum, bool $context, array $permissions, array $throttle): ?string
    {
        if (! $sanctum) {
            return str_starts_with($uri, self::PREFIX.'/auth/') && $throttle !== []
                ? null
                : 'route publique hors authentification ou sans limite de débit';
        }

        if (! $context) {
            return 'contexte fournisseur absent';
        }

        if ($throttle === []) {
            return 'aucune limite de débit';
        }

        return $permissions === [] && ! array_key_exists($uri, self::SESSION_BOUND)
            ? 'aucune permission ni contrôle documenté'
            : null;
    }

    private function isolationOf(string $uri): string
    {
        $best = '';

        foreach (array_keys(self::ISOLATION) as $prefix) {
            if (($uri === $prefix || str_starts_with($uri, $prefix.'/')) && strlen($prefix) > strlen($best)) {
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
