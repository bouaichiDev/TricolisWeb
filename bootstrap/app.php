<?php

use App\Console\Commands\CheckStatusMachine;
use App\Console\Commands\GrantPlatformAdmin;
use App\Console\Commands\ImportStatusCodes;
use App\Console\Commands\RepairSiteAddressLinks;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AuthenticateCustomerApiKey;
use App\Http\Middleware\EnsureCustomerPortalContext;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Http\Middleware\EnsureProviderPortalContext;
use App\Http\Middleware\RejectCustomerPortalToken;
use App\Http\Middleware\RequireCustomerPermission;
use App\Http\Middleware\RequireProviderPermission;
use App\Modules\Communications\Console\ProcessScheduledCommunications;
use App\Modules\CustomerPortal\Console\PortalRouteMatrix;
use App\Modules\Integrations\Console\PruneCustomerImportUploads;
use App\Modules\Integrations\Console\PullCustomerIntegrations;
use App\Modules\ProviderPortal\Console\ProviderRouteMatrix;
use App\Shared\Http\Errors\ExternalApiErrors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        then: static function (): void {
            // Le portail client : meme backend, espace de routes distinct.
            Route::middleware('api')
                ->prefix('api/v1/customer')
                ->name('customer.')
                ->group(base_path('routes/customer.php'));

            // Le portail fournisseur : même backend, troisième porte.
            Route::middleware('api')
                ->prefix('api/v1/provider')
                ->name('provider.')
                ->group(base_path('routes/provider.php'));

            // L'API publique (lien de suivi) : sans authentification, debit dedie.
            Route::middleware('api')
                ->prefix('api/v1/public')
                ->name('public.')
                ->group(base_path('routes/public.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    // La decouverte automatique ne balaie que `app/Console/Commands` : les
    // commandes des modules doivent etre declarees.
    ->withCommands([
        ProcessScheduledCommunications::class,
        PullCustomerIntegrations::class,
        PortalRouteMatrix::class,
        ProviderRouteMatrix::class,
        PruneCustomerImportUploads::class,
        GrantPlatformAdmin::class,
        RepairSiteAddressLinks::class,
        CheckStatusMachine::class,
        ImportStatusCodes::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'organization' => EnsureOrganizationContext::class,
            // Le portail des clients : une cle API, une adresse autorisee, un
            // droit. Distinct de `auth:sanctum`, qui ouvre l'administration.
            'customer-api' => AuthenticateCustomerApiKey::class,
            // Le portail client (utilisateurs humains) : jeton Sanctum de
            // portail, Customer courant, puis permission `customer.*`.
            'customer.portal' => EnsureCustomerPortalContext::class,
            'customer.permission' => RequireCustomerPermission::class,
            // Le portail fournisseur : jeton Sanctum de portail fournisseur,
            // fournisseur courant, puis permission `provider.*`.
            'provider.portal' => EnsureProviderPortalContext::class,
            'provider.permission' => RequireProviderPermission::class,
            // Et la reciproque : l'interne refuse les jetons des portails.
            'internal.token' => RejectCustomerPortalToken::class,
        ]);
        // Phase 7 : toute réponse de l'API porte ses en-têtes de sécurité.
        // Global plutôt que de groupe : un refus d'authentification (401) est
        // rendu avant les middlewares de groupe, et doit porter ses en-têtes.
        $middleware->append(AddSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        // Phase 8 : le périmètre externe ne voit ni SQL, ni trace, ni classe interne.
        ExternalApiErrors::register($exceptions);
        // Phase 7 : aucun secret ne revient dans les données d'une erreur.
        $exceptions->dontFlash([
            'current_password', 'password', 'password_confirmation', 'credentials',
            'token', 'apiKey', 'api_key', 'secret', 'clientSecret',
        ]);
    })->create();
