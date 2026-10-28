<?php

/*
|--------------------------------------------------------------------------
| Portail fournisseur — /api/v1/provider/*
|--------------------------------------------------------------------------
|
| Même backend que l'interne, autre porte. Toute route authentifiée passe par
| la chaîne complète :
|
|   auth:sanctum → provider.portal → provider.permission:<code>
|
| `provider.portal` résout le fournisseur courant depuis le jeton, jamais
| depuis la requête. Aucune route n'accepte d'identifiant de fournisseur ni
| d'organisation, et aucune ne gère d'utilisateurs ou de rôles : ces
| opérations restent au back-office.
|
*/

use App\Http\Controllers\Api\V1\Provider\ProviderActivationController;
use App\Http\Controllers\Api\V1\Provider\ProviderAuthController;
use App\Http\Controllers\Api\V1\Provider\ProviderContextController;
use App\Http\Controllers\Api\V1\Provider\ProviderDashboardController;
use App\Http\Controllers\Api\V1\Provider\ProviderPasswordController;
use App\Http\Controllers\Api\V1\Provider\Resources\ProviderDriverController;
use App\Http\Controllers\Api\V1\Provider\Resources\ProviderResourceStatusController;
use App\Http\Controllers\Api\V1\Provider\Resources\ProviderVehicleController;
use App\Http\Controllers\Api\V1\Provider\Tours\ProviderTourController;
use App\Http\Controllers\Api\V1\Provider\Tours\ProviderTourExecutionController;
use App\Http\Controllers\Api\V1\Provider\Tours\ProviderTourResourcesController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(static function (): void {
    // Par adresse IP, en plus du compteur adresse/e-mail de l'Action.
    Route::post('login', [ProviderAuthController::class, 'login'])
        ->middleware('throttle:provider-portal-login')
        ->name('login');
    // Débit limité : chaque appel peut déclencher un courriel.
    Route::post('forgot-password', [ProviderPasswordController::class, 'forgot'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::post('reset-password', [ProviderPasswordController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.reset');
    Route::get('invitation', [ProviderActivationController::class, 'show'])
        ->middleware('throttle:20,1')
        ->name('invitation');
    Route::post('activate', [ProviderActivationController::class, 'activate'])
        ->middleware('throttle:10,1')
        ->name('activate');

    Route::middleware(['auth:sanctum', 'provider.portal', 'throttle:provider-portal'])->group(static function (): void {
        Route::post('logout', [ProviderAuthController::class, 'logout'])->name('logout');
        Route::get('me', [ProviderAuthController::class, 'me'])->name('me');
    });
});

Route::middleware(['auth:sanctum', 'provider.portal', 'throttle:provider-portal'])->group(static function (): void {
    Route::get('navigation', [ProviderContextController::class, 'navigation'])->name('navigation');
    Route::get('dashboard', ProviderDashboardController::class)
        ->middleware('provider.permission:provider.dashboard.view')
        ->name('dashboard');
    Route::get('profile', [ProviderContextController::class, 'profile'])
        ->middleware('provider.permission:provider.profile.view')
        ->name('profile.show');
    Route::put('profile', [ProviderContextController::class, 'updateProfile'])->name('profile.update');
    Route::put('profile/password', [ProviderContextController::class, 'changePassword'])->name('profile.password');

    // Phase 3 — tournées attribuées au fournisseur. `calendar` est déclaré
    // avant `{tour}`, qui l'avalerait sinon.
    Route::prefix('tours')->name('tours.')->group(static function (): void {
        Route::get('calendar', [ProviderTourController::class, 'calendar'])
            ->middleware('provider.permission:provider.calendar.view')->name('calendar');
        Route::middleware('provider.permission:provider.tours.view')->group(static function (): void {
            Route::get('/', [ProviderTourController::class, 'index'])->name('index');
            Route::get('{tour}', [ProviderTourController::class, 'show'])->name('show');
        });
        // Phase 4 — exécution : démarrer, terminer, faire avancer les arrêts.
        Route::middleware(['throttle:provider-portal-write', 'provider.permission:provider.tours.view,provider.tours.execute'])->group(static function (): void {
            Route::post('{tour}/start', [ProviderTourExecutionController::class, 'start'])->name('start');
            Route::post('{tour}/complete', [ProviderTourExecutionController::class, 'complete'])->name('complete');
        });
        Route::prefix('{tour}/stops/{stop}')->name('stops.')
            ->middleware(['throttle:provider-portal-write', 'provider.permission:provider.tours.view,provider.tour-stops.update-status'])
            ->group(static function (): void {
                Route::post('arrive', [ProviderTourExecutionController::class, 'arrive'])->name('arrive');
                Route::post('complete', [ProviderTourExecutionController::class, 'completeStop'])->name('complete');
                Route::post('skip', [ProviderTourExecutionController::class, 'skip'])->name('skip');
            });
        Route::patch('{tour}/resources', ProviderTourResourcesController::class)
            ->middleware(['provider.permission:provider.tours.view,provider.tours.assign-resources', 'throttle:provider-portal-write'])
            ->name('resources');
    });

    // Phase 2 — chauffeurs et véhicules du fournisseur courant.
    Route::prefix('drivers')->name('drivers.')->group(static function (): void {
        Route::middleware('provider.permission:provider.drivers.view')->group(static function (): void {
            Route::get('/', [ProviderDriverController::class, 'index'])->name('index');
            Route::get('{driver}', [ProviderDriverController::class, 'show'])->name('show');
        });
        Route::post('/', [ProviderDriverController::class, 'store'])
            ->middleware(['provider.permission:provider.drivers.create', 'throttle:provider-portal-write'])->name('store');
        Route::middleware(['throttle:provider-portal-write', 'provider.permission:provider.drivers.update'])->group(static function (): void {
            Route::patch('{driver}', [ProviderDriverController::class, 'update'])->name('update');
            Route::post('{driver}/deactivate', [ProviderResourceStatusController::class, 'deactivateDriver'])->name('deactivate');
            Route::post('{driver}/activate', [ProviderResourceStatusController::class, 'activateDriver'])->name('activate');
        });
    });

    Route::get('vehicle-types', [ProviderVehicleController::class, 'types'])
        ->middleware('provider.permission:provider.vehicles.view')->name('vehicle-types');
    Route::prefix('vehicles')->name('vehicles.')->group(static function (): void {
        Route::middleware('provider.permission:provider.vehicles.view')->group(static function (): void {
            Route::get('/', [ProviderVehicleController::class, 'index'])->name('index');
            Route::get('{vehicle}', [ProviderVehicleController::class, 'show'])->name('show');
        });
        Route::post('/', [ProviderVehicleController::class, 'store'])
            ->middleware(['provider.permission:provider.vehicles.create', 'throttle:provider-portal-write'])->name('store');
        Route::middleware(['throttle:provider-portal-write', 'provider.permission:provider.vehicles.update'])->group(static function (): void {
            Route::patch('{vehicle}', [ProviderVehicleController::class, 'update'])->name('update');
            Route::post('{vehicle}/deactivate', [ProviderResourceStatusController::class, 'deactivateVehicle'])->name('deactivate');
            Route::post('{vehicle}/activate', [ProviderResourceStatusController::class, 'activateVehicle'])->name('activate');
        });
    });
});
