<?php

use App\Http\Controllers\Api\V1\PublicTracking\PublicTrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API publique — /api/v1/public/*
|--------------------------------------------------------------------------
|
| Aucune authentification : le client final ouvre un lien reçu par SMS ou
| courriel, sans compte Tricolis. Le secret est le jeton du lien lui-même.
|
| Débit dédié (`public-tracking`, par adresse IP), distinct de celui de
| l'API authentifiée : c'est la seule porte que n'importe qui peut essayer.
|
*/

Route::middleware('throttle:public-tracking')->prefix('tracking')->name('tracking.')->group(static function (): void {
    Route::get('{token}', [PublicTrackingController::class, 'show'])->name('show');
    Route::get('{token}/logo', [PublicTrackingController::class, 'logo'])->name('logo');
});
