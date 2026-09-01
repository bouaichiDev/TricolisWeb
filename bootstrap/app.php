<?php

use App\Console\Commands\CheckStatusMachine;
use App\Console\Commands\GrantPlatformAdmin;
use App\Console\Commands\ImportStatusCodes;
use App\Console\Commands\RepairSiteAddressLinks;
use App\Console\Commands\SyncOrganizationMenus;
use App\Http\Middleware\AuthenticateCustomerApiKey;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Modules\Communications\Console\ProcessScheduledCommunications;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    // La decouverte automatique ne balaie que `app/Console/Commands` : les
    // commandes des modules doivent etre declarees.
    ->withCommands([
        ProcessScheduledCommunications::class,
        GrantPlatformAdmin::class,
        RepairSiteAddressLinks::class,
        SyncOrganizationMenus::class,
        CheckStatusMachine::class,
        ImportStatusCodes::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'organization' => EnsureOrganizationContext::class,
            // Le portail des clients : une cle API, une adresse autorisee, un
            // droit. Distinct de `auth:sanctum`, qui ouvre l'administration.
            'customer-api' => AuthenticateCustomerApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
