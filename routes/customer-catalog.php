<?php

use App\Http\Controllers\Api\V1\Customer\Catalog\CustomerCatalogController;
use App\Http\Controllers\Api\V1\Customer\Catalog\CustomerCatalogItemWriteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portail client — catalogue (annoncé en Phase 1, livré en Phase 8)
|--------------------------------------------------------------------------
|
| Inclus depuis `customer.php`, dans le groupe `auth:sanctum` →
| `customer.portal`. Les permissions `customer.catalog.*` et
| `customer.articles.*` sont en outre bornées par `customers.catalog_enabled`
| (`CustomerFeatureGate`). Lecture et ajout d'articles seulement.
|
*/

Route::prefix('catalog')->name('catalog.')->group(static function (): void {
    Route::get('catalogs', [CustomerCatalogController::class, 'catalogs'])
        ->middleware('customer.permission:customer.catalog.view')
        ->name('catalogs.index');

    Route::middleware('customer.permission:customer.catalog.view,customer.articles.view')->group(static function (): void {
        Route::get('items', [CustomerCatalogController::class, 'index'])->name('items.index');
        Route::get('items/{item}', [CustomerCatalogController::class, 'show'])->name('items.show');
    });

    Route::post('items', [CustomerCatalogController::class, 'store'])
        ->middleware('customer.permission:customer.catalog.view,customer.articles.create')
        ->name('items.store');

    // Modifier / désactiver / réactiver : l'article est cherché dans la portée
    // du client (CustomerCatalogScope), jamais par son seul identifiant.
    Route::middleware('customer.permission:customer.catalog.view,customer.articles.update')->group(static function (): void {
        Route::patch('items/{item}', [CustomerCatalogItemWriteController::class, 'update'])->name('items.update');
        Route::post('items/{item}/deactivate', [CustomerCatalogItemWriteController::class, 'deactivate'])->name('items.deactivate');
        Route::post('items/{item}/activate', [CustomerCatalogItemWriteController::class, 'activate'])->name('items.activate');
    });
});
