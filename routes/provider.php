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
});
