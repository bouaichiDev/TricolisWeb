<?php

use App\Http\Controllers\Api\V1\Addresses\AddressContactController;
use App\Http\Controllers\Api\V1\Addresses\AddressController;
use App\Http\Controllers\Api\V1\Addresses\AddressLinkController;
use App\Http\Controllers\Api\V1\Agencies\AgencyController;
use App\Http\Controllers\Api\V1\Agencies\DepotController;
use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Catalogs\CustomerCatalogController;
use App\Http\Controllers\Api\V1\Catalogs\CustomerCatalogItemController;
use App\Http\Controllers\Api\V1\Contacts\ContactController;
use App\Http\Controllers\Api\V1\Contacts\ContactLinkController;
use App\Http\Controllers\Api\V1\Customers\CustomerController;
use App\Http\Controllers\Api\V1\Customers\CustomerSiteController;
use App\Http\Controllers\Api\V1\Documents\DocumentController;
use App\Http\Controllers\Api\V1\Documents\DocumentLinkController;
use App\Http\Controllers\Api\V1\Identity\OrganizationUserController;
use App\Http\Controllers\Api\V1\Identity\PermissionController;
use App\Http\Controllers\Api\V1\Identity\RoleController;
use App\Http\Controllers\Api\V1\Identity\UserController;
use App\Http\Controllers\Api\V1\Orders\OrderController;
use App\Http\Controllers\Api\V1\Orders\ServiceController;
use App\Http\Controllers\Api\V1\Organizations\OrganizationController;
use App\Http\Controllers\Api\V1\Organizations\SubscriptionController;
use App\Http\Controllers\Api\V1\Packages\GroupingTypeController;
use App\Http\Controllers\Api\V1\Packages\PackageTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(static function (): void {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->name('password.email');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');

    Route::middleware('auth:sanctum')->group(static function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile');
        Route::patch('password', [ProfileController::class, 'updatePassword'])->name('password');
        Route::get('sessions', [SessionController::class, 'index'])->name('sessions');
        Route::delete('sessions/{tokenId}', [SessionController::class, 'destroy'])->name('sessions.revoke');
    });
});

Route::middleware('auth:sanctum')->group(static function (): void {
    Route::apiResource('organizations', OrganizationController::class)->except(['create', 'edit']);

    Route::middleware('organization')->group(static function (): void {
        Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
        Route::post('subscription', [SubscriptionController::class, 'store'])->name('subscription.store');
        Route::patch('subscription', [SubscriptionController::class, 'update'])->name('subscription.update');
        Route::delete('subscription', [SubscriptionController::class, 'destroy'])->name('subscription.destroy');
        Route::apiResource('agencies', AgencyController::class)->except(['create', 'edit']);
        Route::apiResource('agencies.depots', DepotController::class)->except(['create', 'edit']);
        Route::patch('customers/{customer}/status', [CustomerController::class, 'updateStatus'])->name('customers.status');
        Route::apiResource('customers', CustomerController::class)->except(['create', 'edit']);
        Route::apiResource('customers.sites', CustomerSiteController::class)->except(['create', 'edit']);
        Route::apiResource('customers.catalogs', CustomerCatalogController::class)->except(['create', 'edit']);
        Route::apiResource('customers.catalogs.items', CustomerCatalogItemController::class)
            ->parameters(['items' => 'item'])
            ->except(['create', 'edit']);
        Route::get('addresses/{address}/links', [AddressLinkController::class, 'index'])->name('addresses.links.index');
        Route::post('addresses/{address}/links', [AddressLinkController::class, 'store'])->name('addresses.links.store');
        Route::delete('addresses/{address}/links/{link}', [AddressLinkController::class, 'destroy'])->name('addresses.links.destroy');
        Route::get('addresses/{address}/contacts', [AddressContactController::class, 'index'])->name('addresses.contacts.index');
        Route::post('addresses/{address}/contacts', [AddressContactController::class, 'store'])->name('addresses.contacts.store');
        Route::delete('addresses/{address}/contacts/{addressContact}', [AddressContactController::class, 'destroy'])->name('addresses.contacts.destroy');
        Route::apiResource('addresses', AddressController::class)->except(['create', 'edit']);
        Route::get('contacts/{contact}/links', [ContactLinkController::class, 'index'])->name('contacts.links.index');
        Route::post('contacts/{contact}/links', [ContactLinkController::class, 'store'])->name('contacts.links.store');
        Route::delete('contacts/{contact}/links/{link}', [ContactLinkController::class, 'destroy'])->name('contacts.links.destroy');
        Route::apiResource('contacts', ContactController::class)->except(['create', 'edit']);
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::get('documents/{document}/links', [DocumentLinkController::class, 'index'])->name('documents.links.index');
        Route::post('documents/{document}/links', [DocumentLinkController::class, 'store'])->name('documents.links.store');
        Route::delete('documents/{document}/links/{link}', [DocumentLinkController::class, 'destroy'])->name('documents.links.destroy');
        Route::apiResource('documents', DocumentController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::get('permissions/{permission}', [PermissionController::class, 'show'])->name('permissions.show');
        Route::apiResource('roles', RoleController::class)->except(['create', 'edit']);
        Route::apiResource('users', UserController::class)->except(['create', 'edit']);
        Route::apiResource('organization-users', OrganizationUserController::class)->except(['create', 'edit']);
        Route::apiResource('services', ServiceController::class)->except(['create', 'edit']);
        Route::get('package-types', [PackageTypeController::class, 'index'])->name('package-types.index');
        Route::post('package-types', [PackageTypeController::class, 'store'])->name('package-types.store');
        Route::patch('package-types/{packageType}', [PackageTypeController::class, 'update'])->name('package-types.update');
        Route::delete('package-types/{packageType}', [PackageTypeController::class, 'destroy'])->name('package-types.destroy');
        Route::get('package-grouping-types', [GroupingTypeController::class, 'index'])->name('package-grouping-types.index');
        Route::post('package-grouping-types', [GroupingTypeController::class, 'store'])->name('package-grouping-types.store');
        Route::patch('package-grouping-types/{groupingType}', [GroupingTypeController::class, 'update'])->name('package-grouping-types.update');
        Route::delete('package-grouping-types/{groupingType}', [GroupingTypeController::class, 'destroy'])->name('package-grouping-types.destroy');
        Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show', 'destroy']);
    });
});
