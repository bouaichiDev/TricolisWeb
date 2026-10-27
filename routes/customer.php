<?php

use App\Http\Controllers\Api\V1\Customer\Calendar\CustomerCalendarController;
use App\Http\Controllers\Api\V1\Customer\CustomerActivationController;
use App\Http\Controllers\Api\V1\Customer\CustomerAuthController;
use App\Http\Controllers\Api\V1\Customer\CustomerContextController;
use App\Http\Controllers\Api\V1\Customer\CustomerDashboardController;
use App\Http\Controllers\Api\V1\Customer\CustomerPasswordController;
use App\Http\Controllers\Api\V1\Customer\CustomerUserAccessController;
use App\Http\Controllers\Api\V1\Customer\CustomerUserController;
use App\Http\Controllers\Api\V1\Customer\Orders\CustomerOrderActionController;
use App\Http\Controllers\Api\V1\Customer\Orders\CustomerOrderContentController;
use App\Http\Controllers\Api\V1\Customer\Orders\CustomerOrderController;
use App\Http\Controllers\Api\V1\Customer\Orders\CustomerOrderDocumentController;
use App\Http\Controllers\Api\V1\Customer\Orders\CustomerOrderFormOptionsController;
use App\Http\Controllers\Api\V1\Customer\Orders\CustomerOrderHistoryController;
use App\Http\Controllers\Api\V1\Customer\Orders\CustomerOrderTrackingController;
use App\Http\Controllers\Api\V1\Customer\Orders\CustomerPublicTrackingLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portail client — /api/v1/customer/*
|--------------------------------------------------------------------------
|
| Meme backend que l'interne, autre porte. Toute route authentifiee passe par
| la chaine complete :
|
|   auth:sanctum → customer.portal → customer.permission:<code>
|
| `customer.portal` resout le Customer courant depuis le jeton, jamais depuis
| la requete. Les routes n'acceptent donc aucun identifiant de client : il n'y
| a rien a falsifier.
|
*/

Route::prefix('auth')->name('auth.')->group(static function (): void {
    // Par adresse IP, en plus du compteur adresse/e-mail de l'Action (Phase 7).
    Route::post('login', [CustomerAuthController::class, 'login'])
        ->middleware('throttle:customer-portal-login')
        ->name('login');
    // Debit limite : chaque appel peut declencher un courriel.
    Route::post('forgot-password', [CustomerPasswordController::class, 'forgot'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::post('reset-password', [CustomerPasswordController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.reset');
    Route::get('invitation', [CustomerActivationController::class, 'show'])
        ->middleware('throttle:20,1')
        ->name('invitation');
    Route::post('activate', [CustomerActivationController::class, 'activate'])
        ->middleware('throttle:10,1')
        ->name('activate');

    Route::middleware(['auth:sanctum', 'customer.portal', 'throttle:customer-portal'])->group(static function (): void {
        Route::post('logout', [CustomerAuthController::class, 'logout'])->name('logout');
        Route::get('me', [CustomerAuthController::class, 'me'])->name('me');
    });
});

Route::middleware(['auth:sanctum', 'customer.portal', 'throttle:customer-portal'])->group(static function (): void {
    Route::get('context', [CustomerContextController::class, 'context'])->name('context');
    // Tableau de bord : chaque widget est gardé par sa propre permission.
    Route::get('dashboard', CustomerDashboardController::class)->name('dashboard');
    Route::get('permissions', [CustomerContextController::class, 'permissions'])->name('permissions');
    Route::get('profile', [CustomerContextController::class, 'profile'])->name('profile.show');
    Route::put('profile', [CustomerContextController::class, 'updateProfile'])->name('profile.update');

    Route::get('roles', [CustomerContextController::class, 'roles'])
        ->middleware('customer.permission:customer.roles.view')
        ->name('roles.index');

    Route::prefix('users')->name('users.')->group(static function (): void {
        Route::middleware('customer.permission:customer.users.view')->group(static function (): void {
            Route::get('/', [CustomerUserController::class, 'index'])->name('index');
            Route::get('{id}', [CustomerUserController::class, 'show'])->name('show');
        });

        Route::post('invite', [CustomerUserController::class, 'invite'])
            ->middleware('customer.permission:customer.users.invite')
            ->name('invite');
        Route::put('{id}', [CustomerUserController::class, 'update'])
            ->middleware('customer.permission:customer.users.update')
            ->name('update');
        Route::post('{id}/enable', [CustomerUserAccessController::class, 'enable'])
            ->middleware('customer.permission:customer.users.disable')
            ->name('enable');
        Route::post('{id}/disable', [CustomerUserAccessController::class, 'disable'])
            ->middleware('customer.permission:customer.users.disable')
            ->name('disable');
        Route::post('{id}/resend-invitation', [CustomerUserAccessController::class, 'resendInvitation'])
            ->middleware('customer.permission:customer.users.invite')
            ->name('resend-invitation');
        Route::put('{id}/roles', [CustomerUserAccessController::class, 'roles'])
            ->middleware('customer.permission:customer.users.assign_role')
            ->name('roles');
    });

    /*
    | Commandes (Phase 2). La permission est déclarée route par route ; la
    | règle métier (modifiable, annulable) est ensuite jugée par le backend.
    */
    Route::middleware('customer.permission:customer.orders.create')->group(static function (): void {
        Route::post('orders', [CustomerOrderController::class, 'store'])->name('orders.store');
    });

    // Référentiels du formulaire : utiles à qui crée ou modifie.
    Route::get('order-form-options', [CustomerOrderFormOptionsController::class, 'options'])
        ->middleware('customer.permission:customer.orders.view')
        ->name('orders.form-options');
    Route::get('catalog-items', [CustomerOrderFormOptionsController::class, 'catalogItems'])
        ->middleware('customer.permission:customer.orders.view')
        ->name('catalog-items.index');

    Route::prefix('orders')->name('orders.')->group(static function (): void {
        Route::middleware('customer.permission:customer.orders.view')->group(static function (): void {
            Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
            Route::get('{order}', [CustomerOrderController::class, 'show'])->name('show');
            Route::get('{order}/services', [CustomerOrderContentController::class, 'services'])->name('services');
            Route::get('{order}/packages', [CustomerOrderContentController::class, 'packages'])->name('packages');
            Route::get('{order}/lines', [CustomerOrderContentController::class, 'lines'])->name('lines');
        });

        Route::put('{order}', [CustomerOrderController::class, 'update'])
            ->middleware('customer.permission:customer.orders.update')
            ->name('update');
        Route::post('{order}/duplicate', [CustomerOrderActionController::class, 'duplicate'])
            ->middleware('customer.permission:customer.orders.duplicate')
            ->name('duplicate');
        Route::post('{order}/cancel', [CustomerOrderActionController::class, 'cancel'])
            ->middleware('customer.permission:customer.orders.cancel')
            ->name('cancel');

        Route::middleware('customer.permission:customer.orders.documents.view')->group(static function (): void {
            Route::get('{order}/documents', [CustomerOrderDocumentController::class, 'index'])->name('documents.index');
            Route::get('{order}/documents/{document}/download', [CustomerOrderDocumentController::class, 'download'])->name('documents.download');
        });
        Route::post('{order}/documents', [CustomerOrderDocumentController::class, 'store'])
            ->middleware('customer.permission:customer.orders.documents.upload')
            ->name('documents.store');

        Route::get('{order}/history', CustomerOrderHistoryController::class)
            ->middleware('customer.permission:customer.orders.history.view')
            ->name('history');

        /*
        | Suivi (Phase 3). La permission du module est sur la route ; celle de
        | suivre une commande (`customer.orders.track`) est revérifiée ensuite.
        */
        Route::middleware('customer.permission:customer.tracking.view')->group(static function (): void {
            Route::get('{order}/tracking', [CustomerOrderTrackingController::class, 'show'])->name('tracking.show');
            Route::get('{order}/tracking/services/{service}', [CustomerOrderTrackingController::class, 'service'])->name('tracking.service');
        });

        Route::get('{order}/public-tracking-link', [CustomerPublicTrackingLinkController::class, 'show'])
            ->middleware('customer.permission:customer.tracking.public_link.view')
            ->name('public-tracking-link.show');
        Route::middleware('customer.permission:customer.tracking.public_link.manage')->group(static function (): void {
            Route::post('{order}/public-tracking-link', [CustomerPublicTrackingLinkController::class, 'store'])->name('public-tracking-link.store');
            Route::delete('{order}/public-tracking-link', [CustomerPublicTrackingLinkController::class, 'destroy'])->name('public-tracking-link.destroy');
        });
    });

    // Après-livraison (Phase 4) : documents de service, BL, POD, réclamations.
    require __DIR__.'/customer-after-delivery.php';

    // Stock (Phase 5) : articles, balances, mouvements, réservations — lecture seule sur les quantités.
    require __DIR__.'/customer-stock.php';

    // Intégrations (Phase 6) : flux, envois et dépôt de fichiers de commandes — sans aucun secret.
    require __DIR__.'/customer-integrations.php';

    // Catalogue (annoncé en Phase 1, livré en Phase 8) : consultation et ajout d'articles.
    require __DIR__.'/customer-catalog.php';

    // Calendrier (Phase 3) : les commandes du client sur la période visible.
    Route::get('calendar/orders', CustomerCalendarController::class)
        ->middleware('customer.permission:customer.calendar.view')
        ->name('calendar.orders');
});
