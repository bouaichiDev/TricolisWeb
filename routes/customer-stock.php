<?php

use App\Http\Controllers\Api\V1\Customer\Orders\CustomerOrderFormOptionsController;
use App\Http\Controllers\Api\V1\Customer\Stock\CustomerStockAdjustmentRequestController;
use App\Http\Controllers\Api\V1\Customer\Stock\CustomerStockItemController;
use App\Http\Controllers\Api\V1\Customer\Stock\CustomerStockMovementController;
use App\Http\Controllers\Api\V1\Customer\Stock\CustomerStockReservationController;
use App\Http\Controllers\Api\V1\Customer\Stock\CustomerStockSummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portail client — stock (Phase 5)
|--------------------------------------------------------------------------
|
| Inclus depuis `customer.php`, **dans** le groupe `auth:sanctum` →
| `customer.portal`. Toute permission `customer.stock.*` est en outre bornée
| par `customers.stock_enabled` (`CustomerFeatureGate`).
|
| Lecture seule sur les quantités : aucune route n'écrit une balance, un
| mouvement ou une réservation. Seuls les articles se créent et se
| corrigent — leurs métadonnées, jamais leur stock.
|
| Les demandes de correction se déposent et se lisent ici ; elles ne
| modifient jamais le stock — le transporteur les tranche en interne.
|
*/

Route::prefix('stock')->name('stock.')->group(static function (): void {
    Route::middleware('customer.permission:customer.stock.view')->group(static function (): void {
        Route::get('summary', CustomerStockSummaryController::class)->name('summary');
        Route::get('items', [CustomerStockItemController::class, 'index'])->name('items.index');
        Route::get('items/{stockItem}', [CustomerStockItemController::class, 'show'])->name('items.show');
        Route::get('items/{stockItem}/balances', [CustomerStockItemController::class, 'balances'])->name('items.balances');
    });

    Route::post('items', [CustomerStockItemController::class, 'store'])
        ->middleware('customer.permission:customer.stock.items.create')
        ->name('items.store');
    Route::patch('items/{stockItem}', [CustomerStockItemController::class, 'update'])
        ->middleware('customer.permission:customer.stock.items.update')
        ->name('items.update');

    // Le catalogue du client, pour rattacher un article : même lecture que le
    // formulaire de commande, sous la permission de créer un article.
    Route::get('catalog-items', [CustomerOrderFormOptionsController::class, 'catalogItems'])
        ->middleware('customer.permission:customer.stock.items.create')
        ->name('catalog-items');

    Route::middleware('customer.permission:customer.stock.movements.view')->group(static function (): void {
        Route::get('movements', [CustomerStockMovementController::class, 'index'])->name('movements.index');
        Route::get('movements/{stockMovement}', [CustomerStockMovementController::class, 'show'])->name('movements.show');
    });

    Route::middleware('customer.permission:customer.stock.adjustment_requests.view')->group(static function (): void {
        Route::get('adjustment-requests', [CustomerStockAdjustmentRequestController::class, 'index'])->name('adjustment-requests.index');
        Route::get('adjustment-requests/{stockAdjustmentRequest}', [CustomerStockAdjustmentRequestController::class, 'show'])->name('adjustment-requests.show');
    });
    Route::post('adjustment-requests', [CustomerStockAdjustmentRequestController::class, 'store'])
        ->middleware('customer.permission:customer.stock.adjustment_requests.create')
        ->name('adjustment-requests.store');

    Route::middleware('customer.permission:customer.stock.reservations.view')->group(static function (): void {
        Route::get('reservations', [CustomerStockReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservations/{stockReservation}', [CustomerStockReservationController::class, 'show'])->name('reservations.show');
    });
});
