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
use App\Http\Controllers\Api\V1\Orders\OrderDocumentController;
use App\Http\Controllers\Api\V1\Orders\OrderHistoryController;
use App\Http\Controllers\Api\V1\Orders\OrderLineController;
use App\Http\Controllers\Api\V1\Orders\OrderServiceContactController;
use App\Http\Controllers\Api\V1\Orders\OrderServiceController;
use App\Http\Controllers\Api\V1\Orders\ServiceController;
use App\Http\Controllers\Api\V1\Organizations\OrganizationController;
use App\Http\Controllers\Api\V1\Organizations\SubscriptionController;
use App\Http\Controllers\Api\V1\Packages\GroupingTypeController;
use App\Http\Controllers\Api\V1\Packages\PackageController;
use App\Http\Controllers\Api\V1\Packages\PackageLineController;
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
        Route::patch('orders/{order}/services/{orderService}/status', [OrderServiceController::class, 'updateStatus'])->name('orders.services.status');
        Route::get('orders/{order}/services/{orderService}/contacts', [OrderServiceContactController::class, 'index'])->name('orders.services.contacts.index');
        Route::post('orders/{order}/services/{orderService}/contacts', [OrderServiceContactController::class, 'store'])->name('orders.services.contacts.store');
        Route::patch('orders/{order}/services/{orderService}/contacts/{contact}', [OrderServiceContactController::class, 'update'])->name('orders.services.contacts.update');
        Route::delete('orders/{order}/services/{orderService}/contacts/{contact}', [OrderServiceContactController::class, 'destroy'])->name('orders.services.contacts.destroy');
        Route::apiResource('orders.services', OrderServiceController::class)
            ->parameters(['services' => 'orderService'])
            ->except(['create', 'edit']);
        Route::get('orders/{order}/packages/tree', [PackageController::class, 'tree'])->name('orders.packages.tree');
        Route::post('orders/{order}/packages/{package}/lines', [PackageLineController::class, 'store'])->name('orders.packages.lines.store');
        Route::patch('orders/{order}/packages/{package}/lines/{line}', [PackageLineController::class, 'update'])->name('orders.packages.lines.update');
        Route::delete('orders/{order}/packages/{package}/lines/{line}', [PackageLineController::class, 'destroy'])->name('orders.packages.lines.destroy');
        Route::apiResource('orders.packages', PackageController::class)->except(['create', 'edit']);
        Route::post('orders/{order}/duplicate', [OrderController::class, 'duplicate'])->name('orders.duplicate');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::get('orders/{order}/history', [OrderHistoryController::class, 'index'])->name('orders.history');
        Route::get('orders/{order}/documents', [OrderDocumentController::class, 'index'])->name('orders.documents.index');
        Route::post('orders/{order}/documents', [OrderDocumentController::class, 'store'])->name('orders.documents.store');
        Route::apiResource('orders.lines', OrderLineController::class)
            ->parameters(['lines' => 'line'])
            ->except(['create', 'edit']);
        Route::apiResource('orders', OrderController::class)->except(['create', 'edit']);
    });
});
