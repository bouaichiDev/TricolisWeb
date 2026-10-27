<?php

use App\Http\Controllers\Api\V1\Customer\Integrations\CustomerExportJobController;
use App\Http\Controllers\Api\V1\Customer\Integrations\CustomerImportController;
use App\Http\Controllers\Api\V1\Customer\Integrations\CustomerImportRunController;
use App\Http\Controllers\Api\V1\Customer\Integrations\CustomerIntegrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portail client — intégrations (Phase 6)
|--------------------------------------------------------------------------
|
| Inclus depuis `customer.php`, **dans** le groupe `auth:sanctum` →
| `customer.portal`. Toutes les lectures sont bornées au client courant
| (`CustomerIntegrationScope`) ; aucun `customerId` n'est accepté.
|
| Le client voit ses flux et leurs envois, dépose ses fichiers de commandes et
| relance un envoi en échec. Il n'administre ni connexion, ni secret, ni clé
| API : cela reste au back-office du transporteur.
|
*/

Route::prefix('integrations')->name('integrations.')->group(static function (): void {
    Route::middleware('customer.permission:customer.integrations.view')->group(static function (): void {
        Route::get('/', [CustomerIntegrationController::class, 'overview'])->name('overview');
        Route::get('import-configurations', [CustomerIntegrationController::class, 'importConfigurations'])->name('import-configurations');
        Route::get('export-configurations', [CustomerIntegrationController::class, 'exportConfigurations'])->name('export-configurations');
        Route::get('api-configurations', [CustomerIntegrationController::class, 'apiConfigurations'])->name('api-configurations');
    });

    Route::middleware('customer.permission:customer.integrations.view,customer.integrations.logs.view')->group(static function (): void {
        Route::get('export-jobs', [CustomerExportJobController::class, 'index'])->name('export-jobs.index');
        Route::get('export-jobs/{exportJob}', [CustomerExportJobController::class, 'show'])->name('export-jobs.show');
        Route::get('import-runs', [CustomerImportRunController::class, 'index'])->name('import-runs.index');
        Route::get('import-runs/{importRun}', [CustomerImportRunController::class, 'show'])->name('import-runs.show');
    });

    Route::post('export-jobs/{exportJob}/retry', [CustomerExportJobController::class, 'retry'])
        ->middleware(['customer.permission:customer.integrations.view,customer.integrations.export_jobs.retry', 'throttle:customer-portal-retry'])
        ->name('export-jobs.retry');

    Route::middleware('customer.permission:customer.integrations.view,customer.integrations.import')->group(static function (): void {
        Route::post('imports/preview', [CustomerImportController::class, 'preview'])->middleware('throttle:customer-portal-import')->name('imports.preview');
        Route::post('imports/execute', [CustomerImportController::class, 'execute'])->middleware('throttle:customer-portal-import')->name('imports.execute');
        Route::get('imports/{token}', [CustomerImportController::class, 'result'])->name('imports.result');
    });
});
