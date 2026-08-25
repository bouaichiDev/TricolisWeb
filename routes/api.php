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
use App\Http\Controllers\Api\V1\Billing\InvoiceController;
use App\Http\Controllers\Api\V1\Billing\InvoiceLineController;
use App\Http\Controllers\Api\V1\Catalogs\CustomerCatalogController;
use App\Http\Controllers\Api\V1\Catalogs\CustomerCatalogItemController;
use App\Http\Controllers\Api\V1\Claims\ClaimController;
use App\Http\Controllers\Api\V1\Communications\CommunicationAttachmentController;
use App\Http\Controllers\Api\V1\Communications\CommunicationRuleController;
use App\Http\Controllers\Api\V1\Communications\CommunicationTemplateController;
use App\Http\Controllers\Api\V1\Communications\OrderCommunicationController;
use App\Http\Controllers\Api\V1\Communications\OrderCommunicationStateController;
use App\Http\Controllers\Api\V1\Contacts\ContactController;
use App\Http\Controllers\Api\V1\Contacts\ContactLinkController;
use App\Http\Controllers\Api\V1\Customers\CustomerController;
use App\Http\Controllers\Api\V1\Customers\CustomerSiteController;
use App\Http\Controllers\Api\V1\Documents\DocumentController;
use App\Http\Controllers\Api\V1\Documents\DocumentLinkController;
use App\Http\Controllers\Api\V1\Drivers\DriverController;
use App\Http\Controllers\Api\V1\Exports\ExportConfigurationController;
use App\Http\Controllers\Api\V1\Exports\ExportJobController;
use App\Http\Controllers\Api\V1\Fleet\VehicleController;
use App\Http\Controllers\Api\V1\Fleet\VehicleTypeController;
use App\Http\Controllers\Api\V1\Identity\OrganizationUserController;
use App\Http\Controllers\Api\V1\Identity\PermissionController;
use App\Http\Controllers\Api\V1\Identity\RoleController;
use App\Http\Controllers\Api\V1\Identity\UserController;
use App\Http\Controllers\Api\V1\Integrations\ApiConfigurationController;
use App\Http\Controllers\Api\V1\Integrations\ImportConfigurationController;
use App\Http\Controllers\Api\V1\Integrations\OrganizationApiConfigurationController;
use App\Http\Controllers\Api\V1\Orders\OrderController;
use App\Http\Controllers\Api\V1\Orders\OrderDocumentController;
use App\Http\Controllers\Api\V1\Orders\OrderHistoryController;
use App\Http\Controllers\Api\V1\Orders\OrderLineController;
use App\Http\Controllers\Api\V1\Orders\OrderServiceContactController;
use App\Http\Controllers\Api\V1\Orders\OrderServiceController;
use App\Http\Controllers\Api\V1\Orders\OrderServicePackageController;
use App\Http\Controllers\Api\V1\Orders\OrderStockPlanController;
use App\Http\Controllers\Api\V1\Orders\ServiceController;
use App\Http\Controllers\Api\V1\Organizations\MenuController;
use App\Http\Controllers\Api\V1\Organizations\OrganizationController;
use App\Http\Controllers\Api\V1\Organizations\SubscriptionController;
use App\Http\Controllers\Api\V1\Packages\GroupingTypeController;
use App\Http\Controllers\Api\V1\Packages\PackageController;
use App\Http\Controllers\Api\V1\Packages\PackageLineController;
use App\Http\Controllers\Api\V1\Packages\PackageTypeController;
use App\Http\Controllers\Api\V1\ProofOfDelivery\ProofOfDeliveryController;
use App\Http\Controllers\Api\V1\Providers\ProviderController;
use App\Http\Controllers\Api\V1\ProviderSettlements\ProviderSettlementController;
use App\Http\Controllers\Api\V1\ProviderSettlements\ProviderSettlementLineController;
use App\Http\Controllers\Api\V1\Statuses\StatusController;
use App\Http\Controllers\Api\V1\Statuses\StatusTransitionController;
use App\Http\Controllers\Api\V1\Stock\StockBalanceController;
use App\Http\Controllers\Api\V1\Stock\StockItemController;
use App\Http\Controllers\Api\V1\Stock\StockLocationController;
use App\Http\Controllers\Api\V1\Stock\StockMovementController;
use App\Http\Controllers\Api\V1\Stock\StockReservationController;
use App\Http\Controllers\Api\V1\Tours\TourController;
use App\Http\Controllers\Api\V1\Tours\TourPeriodAssignmentController;
use App\Http\Controllers\Api\V1\Tours\TourPeriodController;
use App\Http\Controllers\Api\V1\Tours\TourStopController;
use App\Http\Controllers\Api\V1\Tours\TourStopServiceController;
use App\Http\Controllers\Api\V1\Tracking\TrackingEventController;
use App\Http\Controllers\Api\V1\Tracking\TrackingEventDefinitionController;
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

    // Le menu effectif se lit sans en-tete d'organisation : un compte
    // plateforme n'en a pas, et le resolveur choisit le catalogue d'apres la
    // portee du compte.
    Route::get('menu', [MenuController::class, 'index'])->name('menu.index');

    // Referentiel des statuts : commun a la plateforme, donc hors du groupe
    // `organization`. Tout membre le lit, seule la plateforme l'ecrit — c'est
    // `StatusPolicy` qui tranche, pas la presence de l'en-tete.
    Route::get('statuses/sources', [StatusController::class, 'sources'])->name('statuses.sources');
    // Le cycle de vie se dessine ici : les transitions sont remplacees d'un
    // bloc, jamais arete par arete.
    Route::get('statuses/{status}/transitions', [StatusTransitionController::class, 'index'])->name('statuses.transitions.index');
    Route::put('statuses/{status}/transitions', [StatusTransitionController::class, 'sync'])->name('statuses.transitions.sync');
    Route::apiResource('statuses', StatusController::class)->except(['create', 'edit']);

    Route::middleware('organization')->group(static function (): void {
        Route::get('menu/catalogue', [MenuController::class, 'catalogue'])->name('menu.catalogue');
        Route::patch('menu', [MenuController::class, 'update'])->name('menu.update');
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
        Route::apiResource('providers', ProviderController::class)->except(['create', 'edit']);
        Route::apiResource('drivers', DriverController::class)->except(['create', 'edit']);
        // Sans ce renommage, Laravel genere le parametre `vehicle_type` et la
        // liaison implicite vers $vehicleType ne se fait pas.
        Route::apiResource('vehicle-types', VehicleTypeController::class)
            ->parameters(['vehicle-types' => 'vehicleType'])
            ->except(['create', 'edit']);
        Route::apiResource('vehicles', VehicleController::class)->except(['create', 'edit']);
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
        // Colis pris en charge par un service : la relation OrderServicePackage
        // du diagramme, jusqu'ici creee a la seule creation de la commande.
        Route::get('orders/{order}/services/{orderService}/packages', [OrderServicePackageController::class, 'index'])->name('orders.services.packages.index');
        Route::post('orders/{order}/services/{orderService}/packages', [OrderServicePackageController::class, 'store'])->name('orders.services.packages.store');
        Route::patch('orders/{order}/services/{orderService}/packages/{servicePackage}', [OrderServicePackageController::class, 'update'])->name('orders.services.packages.update');
        Route::delete('orders/{order}/services/{orderService}/packages/{servicePackage}', [OrderServicePackageController::class, 'destroy'])->name('orders.services.packages.destroy');
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
        // Apercu de la sortie de stock : consulte avant de confirmer, pour
        // faire choisir un emplacement quand la ligne en a plusieurs.
        Route::get('orders/{order}/stock-plan', OrderStockPlanController::class)->name('orders.stock-plan');
        Route::get('orders/{order}/documents', [OrderDocumentController::class, 'index'])->name('orders.documents.index');
        Route::post('orders/{order}/documents', [OrderDocumentController::class, 'store'])->name('orders.documents.store');
        Route::apiResource('orders.lines', OrderLineController::class)
            ->parameters(['lines' => 'line'])
            ->except(['create', 'edit']);
        Route::apiResource('orders', OrderController::class)->except(['create', 'edit']);

        // Planification — les routes `reorder` precedent les apiResource pour
        // qu'aucune ne soit captee comme un identifiant.
        Route::post('tours/{tour}/stops/reorder', [TourStopController::class, 'reorder'])->name('tours.stops.reorder');
        Route::post('tours/{tour}/stops/{tourStop}/services/reorder', [TourStopServiceController::class, 'reorder'])->name('tours.stops.services.reorder');
        Route::post('tours/{tour}/periods/reorder', [TourPeriodController::class, 'reorder'])->name('tours.periods.reorder');

        Route::apiResource('tours.stops.services', TourStopServiceController::class)
            ->parameters(['stops' => 'tourStop', 'services' => 'tourStopService'])
            ->except(['create', 'edit']);
        Route::apiResource('tours.stops', TourStopController::class)
            ->parameters(['stops' => 'tourStop'])
            ->except(['create', 'edit']);
        Route::apiResource('tours.periods.assignments', TourPeriodAssignmentController::class)
            ->parameters(['periods' => 'tourPeriod', 'assignments' => 'assignment'])
            ->except(['create', 'edit']);
        Route::apiResource('tours.periods', TourPeriodController::class)
            ->parameters(['periods' => 'tourPeriod'])
            ->except(['create', 'edit']);
        Route::apiResource('tours', TourController::class)->except(['create', 'edit']);

        // Suivi — pas de PATCH ni de DELETE : un evenement est historique.
        Route::get('orders/{order}/tracking-events', [TrackingEventController::class, 'byOrder'])->name('orders.tracking-events');
        Route::get('orders/{order}/services/{orderService}/tracking-events', [TrackingEventController::class, 'byOrderService'])->name('orders.services.tracking-events');
        Route::get('tours/{tour}/tracking-events', [TrackingEventController::class, 'byTour'])->name('tours.tracking-events');
        Route::get('tours/{tour}/stops/{tourStop}/tracking-events', [TrackingEventController::class, 'byTourStop'])->name('tours.stops.tracking-events');
        Route::get('tracking-events', [TrackingEventController::class, 'index'])->name('tracking-events.index');
        Route::post('tracking-events', [TrackingEventController::class, 'store'])->name('tracking-events.store');
        Route::get('tracking-events/{trackingEvent}', [TrackingEventController::class, 'show'])->name('tracking-events.show');

        // Preuves de livraison — historiques elles aussi.
        Route::get('orders/{order}/proofs-of-delivery', [ProofOfDeliveryController::class, 'byOrder'])->name('orders.proofs-of-delivery.index');
        Route::post('orders/{order}/proofs-of-delivery', [ProofOfDeliveryController::class, 'storeForOrder'])->name('orders.proofs-of-delivery.store');
        Route::get('proofs-of-delivery', [ProofOfDeliveryController::class, 'index'])->name('proofs-of-delivery.index');
        Route::post('proofs-of-delivery', [ProofOfDeliveryController::class, 'store'])->name('proofs-of-delivery.store');
        Route::get('proofs-of-delivery/{proofOfDelivery}', [ProofOfDeliveryController::class, 'show'])->name('proofs-of-delivery.show');

        // Reclamations
        Route::get('customers/{customer}/claims', [ClaimController::class, 'byCustomer'])->name('customers.claims.index');
        Route::post('customers/{customer}/claims', [ClaimController::class, 'storeForCustomer'])->name('customers.claims.store');
        Route::get('orders/{order}/claims', [ClaimController::class, 'byOrder'])->name('orders.claims');
        Route::get('tours/{tour}/claims', [ClaimController::class, 'byTour'])->name('tours.claims');
        Route::apiResource('claims', ClaimController::class)->except(['create', 'edit']);

        // Facturation client
        Route::apiResource('invoices.lines', InvoiceLineController::class)
            ->parameters(['lines' => 'line'])
            ->except(['create', 'edit']);
        Route::apiResource('invoices', InvoiceController::class)->except(['create', 'edit']);

        // Decomptes fournisseurs
        Route::get('providers/{provider}/settlements', [ProviderSettlementController::class, 'byProvider'])->name('providers.settlements.index');
        Route::post('providers/{provider}/settlements', [ProviderSettlementController::class, 'storeForProvider'])->name('providers.settlements.store');
        Route::apiResource('provider-settlements.lines', ProviderSettlementLineController::class)
            ->parameters(['provider-settlements' => 'providerSettlement', 'lines' => 'line'])
            ->except(['create', 'edit']);
        Route::apiResource('provider-settlements', ProviderSettlementController::class)
            ->parameters(['provider-settlements' => 'providerSettlement'])
            ->except(['create', 'edit']);

        // Le parcours client : quels statuts deviennent des etapes visibles.
        Route::apiResource('tracking-event-definitions', TrackingEventDefinitionController::class)
            ->parameters(['tracking-event-definitions' => 'trackingEventDefinition'])
            ->except(['create', 'edit']);

        // Les API externes appelees par l'organisme, sens inverse de
        // customer-api-configurations ou le client nous appelle.
        Route::apiResource('api-configurations', OrganizationApiConfigurationController::class)
            ->parameters(['api-configurations' => 'apiConfiguration'])
            ->except(['create', 'edit']);

        // Stock client
        Route::get('customers/{customer}/stock-items', [StockItemController::class, 'byCustomer'])->name('customers.stock-items.index');
        Route::post('customers/{customer}/stock-items', [StockItemController::class, 'storeForCustomer'])->name('customers.stock-items.store');
        Route::get('customers/{customer}/stock-balances', [StockBalanceController::class, 'byCustomer'])->name('customers.stock-balances.index');
        Route::apiResource('stock-items', StockItemController::class)
            ->parameters(['stock-items' => 'stockItem'])
            ->except(['create', 'edit']);
        // `tree` precede `{stockLocation}`, sinon elle serait captee comme un
        // identifiant.
        Route::get('stock-locations/tree', [StockLocationController::class, 'tree'])->name('stock-locations.tree');
        Route::apiResource('stock-locations', StockLocationController::class)
            ->parameters(['stock-locations' => 'stockLocation'])
            ->except(['create', 'edit']);
        Route::get('stock-balances', [StockBalanceController::class, 'index'])->name('stock-balances.index');
        Route::get('stock-balances/{stockBalance}', [StockBalanceController::class, 'show'])->name('stock-balances.show');
        Route::apiResource('stock-movements', StockMovementController::class)
            ->parameters(['stock-movements' => 'stockMovement'])
            ->only(['index', 'store', 'show']);
        Route::post('stock-reservations/{stockReservation}/release', [StockReservationController::class, 'release'])->name('stock-reservations.release');
        Route::apiResource('stock-reservations', StockReservationController::class)
            ->parameters(['stock-reservations' => 'stockReservation'])
            ->only(['index', 'store', 'show', 'update']);

        // Integrations clients
        Route::get('customers/{customer}/import-configurations', [ImportConfigurationController::class, 'byCustomer'])->name('customers.import-configurations.index');
        Route::post('customers/{customer}/import-configurations', [ImportConfigurationController::class, 'storeForCustomer'])->name('customers.import-configurations.store');
        Route::get('customers/{customer}/api-configurations', [ApiConfigurationController::class, 'byCustomer'])->name('customers.api-configurations.index');
        Route::post('customers/{customer}/api-configurations', [ApiConfigurationController::class, 'storeForCustomer'])->name('customers.api-configurations.store');
        Route::get('customers/{customer}/export-configurations', [ExportConfigurationController::class, 'byCustomer'])->name('customers.export-configurations.index');
        Route::post('customers/{customer}/export-configurations', [ExportConfigurationController::class, 'storeForCustomer'])->name('customers.export-configurations.store');

        Route::apiResource('customer-import-configurations', ImportConfigurationController::class)
            ->parameters(['customer-import-configurations' => 'configuration'])
            ->except(['create', 'edit']);
        // `rotate-key` precede l'apiResource : sans cela `{configuration}` la
        // capterait comme un identifiant.
        Route::post('customer-api-configurations/{configuration}/rotate-key', [ApiConfigurationController::class, 'rotateKey'])->name('customer-api-configurations.rotate-key');
        Route::apiResource('customer-api-configurations', ApiConfigurationController::class)
            ->parameters(['customer-api-configurations' => 'configuration'])
            ->except(['create', 'edit']);
        Route::apiResource('customer-export-configurations', ExportConfigurationController::class)
            ->parameters(['customer-export-configurations' => 'configuration'])
            ->except(['create', 'edit']);

        Route::post('export-jobs/{exportJob}/retry', [ExportJobController::class, 'retry'])->name('export-jobs.retry');
        Route::apiResource('export-jobs', ExportJobController::class)
            ->parameters(['export-jobs' => 'exportJob'])
            ->only(['index', 'store', 'show']);

        // Communication et templates
        Route::apiResource('communication-templates', CommunicationTemplateController::class)
            ->parameters(['communication-templates' => 'communicationTemplate'])
            ->except(['create', 'edit']);
        Route::apiResource('communication-rules', CommunicationRuleController::class)
            ->parameters(['communication-rules' => 'communicationRule'])
            ->except(['create', 'edit']);

        Route::get('orders/{order}/communications', [OrderCommunicationController::class, 'byOrder'])->name('orders.communications.index');
        Route::post('orders/{order}/communications', [OrderCommunicationController::class, 'storeForOrder'])->name('orders.communications.store');

        // Les transitions precedent l'apiResource : sans cela `{orderCommunication}`
        // capterait `queue`, `cancel` et `retry` comme des identifiants.
        Route::post('order-communications/{orderCommunication}/queue', [OrderCommunicationStateController::class, 'queue'])->name('order-communications.queue');
        Route::post('order-communications/{orderCommunication}/cancel', [OrderCommunicationStateController::class, 'cancel'])->name('order-communications.cancel');
        Route::post('order-communications/{orderCommunication}/retry', [OrderCommunicationStateController::class, 'retry'])->name('order-communications.retry');
        Route::apiResource('order-communications.attachments', CommunicationAttachmentController::class)
            ->parameters(['order-communications' => 'orderCommunication', 'attachments' => 'attachment'])
            ->only(['index', 'store', 'show', 'destroy']);
        Route::apiResource('order-communications', OrderCommunicationController::class)
            ->parameters(['order-communications' => 'orderCommunication'])
            ->except(['create', 'edit']);
    });
});
