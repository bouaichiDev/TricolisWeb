<?php

use App\Http\Controllers\Api\V1\Customer\Claims\CustomerClaimController;
use App\Http\Controllers\Api\V1\Customer\Claims\CustomerClaimDocumentController;
use App\Http\Controllers\Api\V1\Customer\Documents\CustomerDocumentController;
use App\Http\Controllers\Api\V1\Customer\Orders\CustomerOrderServiceDocumentController;
use App\Http\Controllers\Api\V1\Customer\Orders\CustomerProofOfDeliveryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portail client — après-livraison (Phase 4)
|--------------------------------------------------------------------------
|
| Inclus depuis `customer.php`, **dans** le groupe `auth:sanctum` →
| `customer.portal` : aucune de ces routes n'existe hors du Customer courant.
|
| Documents et bons de livraison d'un service, preuves de livraison en
| lecture seule, réclamations et leurs pièces jointes.
|
*/
Route::prefix('orders')->name('orders.')->group(static function (): void {
    Route::get('{order}/services/{service}/documents', CustomerOrderServiceDocumentController::class)
        ->middleware('customer.permission:customer.orders.documents.view')
        ->name('services.documents');

    Route::middleware('customer.permission:customer.orders.proofs_of_delivery.view')->group(static function (): void {
        Route::get('{order}/proofs-of-delivery', [CustomerProofOfDeliveryController::class, 'byOrder'])->name('proofs-of-delivery.index');
        Route::get('{order}/services/{service}/proofs-of-delivery', [CustomerProofOfDeliveryController::class, 'byService'])->name('services.proofs-of-delivery');
    });
});

Route::get('proofs-of-delivery/{proofOfDelivery}', [CustomerProofOfDeliveryController::class, 'show'])
    ->middleware('customer.permission:customer.orders.proofs_of_delivery.view')
    ->name('proofs-of-delivery.show');

// Un document par son identifiant : chaque chemin d'accès porte sa permission.
Route::get('documents/{document}', [CustomerDocumentController::class, 'show'])->name('documents.show');
Route::get('documents/{document}/download', [CustomerDocumentController::class, 'download'])->name('documents.download');

Route::prefix('claims')->name('claims.')->group(static function (): void {
    Route::middleware('customer.permission:customer.claims.view')->group(static function (): void {
        Route::get('/', [CustomerClaimController::class, 'index'])->name('index');
        Route::get('{claim}', [CustomerClaimController::class, 'show'])->name('show');
        Route::get('{claim}/documents', [CustomerClaimDocumentController::class, 'index'])->name('documents.index');
    });
    Route::post('/', [CustomerClaimController::class, 'store'])
        ->middleware('customer.permission:customer.claims.create')
        ->name('store');
    Route::patch('{claim}', [CustomerClaimController::class, 'update'])
        ->middleware('customer.permission:customer.claims.update')
        ->name('update');
    Route::post('{claim}/documents', [CustomerClaimDocumentController::class, 'store'])
        ->middleware('customer.permission:customer.claims.create')
        ->name('documents.store');
});
