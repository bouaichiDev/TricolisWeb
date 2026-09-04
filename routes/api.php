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
use App\Http\Controllers\Api\V1\Billing\BillableServiceController;
use App\Http\Controllers\Api\V1\Billing\InvoiceClosureController;
use App\Http\Controllers\Api\V1\Billing\InvoiceController;
use App\Http\Controllers\Api\V1\Billing\InvoiceDocumentController;
use App\Http\Controllers\Api\V1\Billing\InvoiceLineController;
use App\Http\Controllers\Api\V1\Billing\InvoiceRepricingController;
use App\Http\Controllers\Api\V1\Catalogs\CustomerCatalogController;
use App\Http\Controllers\Api\V1\Catalogs\CustomerCatalogItemController;
use App\Http\Controllers\Api\V1\Claims\ClaimController;
use App\Http\Controllers\Api\V1\Client\ClientIdentityController;
use App\Http\Controllers\Api\V1\Client\ClientOrderController;
use App\Http\Controllers\Api\V1\Communications\CommunicationAttachmentController;
use App\Http\Controllers\Api\V1\Communications\CommunicationRuleController;
use App\Http\Controllers\Api\V1\Communications\OrderCommunicationController;
use App\Http\Controllers\Api\V1\Communications\OrderCommunicationStateController;
use App\Http\Controllers\Api\V1\Contacts\ContactController;
use App\Http\Controllers\Api\V1\Contacts\ContactLinkController;
use App\Http\Controllers\Api\V1\Customers\CustomerController;
use App\Http\Controllers\Api\V1\Customers\CustomerSiteController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardWidgetController;
use App\Http\Controllers\Api\V1\Documents\DocumentController;
use App\Http\Controllers\Api\V1\Documents\DocumentLinkController;
use App\Http\Controllers\Api\V1\Drivers\DriverController;
use App\Http\Controllers\Api\V1\Exports\ExportConfigurationController;
use App\Http\Controllers\Api\V1\Exports\ExportJobController;
use App\Http\Controllers\Api\V1\Fleet\VehicleController;
use App\Http\Controllers\Api\V1\Identity\MemberPasswordController;
use App\Http\Controllers\Api\V1\Identity\OrganizationUserController;
use App\Http\Controllers\Api\V1\Identity\PermissionController;
use App\Http\Controllers\Api\V1\Identity\RoleController;
use App\Http\Controllers\Api\V1\Identity\RoleDashboardController;
use App\Http\Controllers\Api\V1\Identity\RoleMenuController;
use App\Http\Controllers\Api\V1\Identity\RoleMenuGroupController;
use App\Http\Controllers\Api\V1\Identity\UserController;
use App\Http\Controllers\Api\V1\Integrations\ApiConfigurationController;
use App\Http\Controllers\Api\V1\Integrations\ImportConfigurationController;
use App\Http\Controllers\Api\V1\Integrations\ImportOrdersController;
use App\Http\Controllers\Api\V1\Integrations\ImportPreviewController;
use App\Http\Controllers\Api\V1\Integrations\OrganizationApiConfigurationController;
use App\Http\Controllers\Api\V1\Integrations\OrganizationMailConfigurationController;
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
use App\Http\Controllers\Api\V1\Organizations\OrganizationLogoController;
use App\Http\Controllers\Api\V1\Organizations\SubscriptionController;
use App\Http\Controllers\Api\V1\Packages\PackageController;
use App\Http\Controllers\Api\V1\Packages\PackageLineController;
use App\Http\Controllers\Api\V1\Planning\PlanningPoolController;
use App\Http\Controllers\Api\V1\Platform\ConfigurationController;
use App\Http\Controllers\Api\V1\Pricing\FormulaController;
use App\Http\Controllers\Api\V1\Pricing\PrebillingController;
use App\Http\Controllers\Api\V1\Pricing\PriceListController;
use App\Http\Controllers\Api\V1\Pricing\PriceMatrixController;
use App\Http\Controllers\Api\V1\Pricing\PriceRuleController;
use App\Http\Controllers\Api\V1\Pricing\PricingVariableController;
use App\Http\Controllers\Api\V1\ProofOfDelivery\ProofOfDeliveryController;
use App\Http\Controllers\Api\V1\Providers\ProviderController;
use App\Http\Controllers\Api\V1\ProviderSettlements\ProviderSettlementController;
use App\Http\Controllers\Api\V1\ProviderSettlements\ProviderSettlementLineController;
use App\Http\Controllers\Api\V1\ProviderSettlements\SettleableServiceController;
use App\Http\Controllers\Api\V1\Statuses\StatusController;
use App\Http\Controllers\Api\V1\Statuses\StatusTransitionController;
use App\Http\Controllers\Api\V1\Stock\StockBalanceController;
use App\Http\Controllers\Api\V1\Stock\StockItemController;
use App\Http\Controllers\Api\V1\Stock\StockLocationController;
use App\Http\Controllers\Api\V1\Stock\StockMovementController;
use App\Http\Controllers\Api\V1\Stock\StockReservationController;
use App\Http\Controllers\Api\V1\Templates\TemplateController;
use App\Http\Controllers\Api\V1\Tours\TourController;
use App\Http\Controllers\Api\V1\Tours\TourPeriodAssignmentController;
use App\Http\Controllers\Api\V1\Tours\TourPeriodController;
use App\Http\Controllers\Api\V1\Tours\TourPlanningController;
use App\Http\Controllers\Api\V1\Tours\TourRouteController;
use App\Http\Controllers\Api\V1\Tours\TourStatusController;
use App\Http\Controllers\Api\V1\Tours\TourStopController;
use App\Http\Controllers\Api\V1\Tours\TourStopServiceController;
use App\Http\Controllers\Api\V1\Tracking\OrderPositionController;
use App\Http\Controllers\Api\V1\Tracking\TrackingEventController;
use App\Http\Controllers\Api\V1\Tracking\TrackingEventDefinitionController;
use App\Http\Controllers\Api\V1\Types\TypeController;
use App\Http\Controllers\Api\V1\Types\TypeItemController;
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

/*
|--------------------------------------------------------------------------
| Portail client
|--------------------------------------------------------------------------
|
| Les routes qu'un client appelle lui-meme, avec sa cle API. Elles vivent a
| part, et c'est deliberе : les routes d'administration scopent par
| **organisation**, et y brancher une cle cliente lui donnerait les donnees de
| tous les clients du transporteur. Ici l'appartenance est une contrainte, pas
| un filtre.
|
| Le droit exige se declare sur chaque route. `client/me` n'en demande aucun :
| une cle sans permission doit pouvoir constater qu'elle n'en a pas.
|
*/
Route::prefix('client')->name('client.')->group(static function (): void {
    Route::middleware('customer-api')->group(static function (): void {
        Route::get('me', ClientIdentityController::class)->name('me');
    });

    Route::middleware('customer-api:orders.view')->group(static function (): void {
        Route::get('orders', [ClientOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [ClientOrderController::class, 'show'])->name('orders.show');
    });
});

Route::middleware('auth:sanctum')->group(static function (): void {
    // Declarees avant la ressource : `{organization}` avalerait le segment.
    // Le logo se sert par ici et non sous /storage : un chemin devinable
    // donnerait celui d'un organisme a qui l'essaie.
    Route::get('organizations/{organization}/logo', [OrganizationLogoController::class, 'show'])->name('organizations.logo.show');
    Route::post('organizations/{organization}/logo', [OrganizationLogoController::class, 'store'])->name('organizations.logo.store');
    Route::delete('organizations/{organization}/logo', [OrganizationLogoController::class, 'destroy'])->name('organizations.logo.destroy');
    Route::apiResource('organizations', OrganizationController::class)->except(['create', 'edit']);

    // La configuration de la plateforme, hors du groupe `organization` : elle
    // ne concerne aucune organisation en particulier, et exiger l'en-tete
    // interdirait l'acces a un compte plateforme, qui n'en a pas.
    //
    // Lire est ouvert a tout compte authentifie : la barre laterale de chacun
    // demande s'il y a un logo par defaut, et proteger cette question
    // obligerait a distribuer une permission plateforme pour afficher une
    // image de marque. Ecrire exige `platform_settings.update`.
    Route::get('configuration', [ConfigurationController::class, 'show'])->name('configuration.show');
    Route::get('configuration/logo', [ConfigurationController::class, 'showLogo'])->name('configuration.logo.show');
    Route::post('configuration/logo', [ConfigurationController::class, 'storeLogo'])->name('configuration.logo.store');
    Route::delete('configuration/logo', [ConfigurationController::class, 'destroyLogo'])->name('configuration.logo.destroy');

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
        // Le tableau de bord est toujours porte par une organisation : les
        // chiffres qu'il agrege en viennent tous. Il vit donc dans ce groupe,
        // contrairement au menu, qu'un compte plateforme lit aussi.
        // `dashboard/widgets` est declaree avant `dashboard` par habitude de
        // lecture ; les deux chemins sont distincts, aucun n'avale l'autre.
        Route::get('dashboard/widgets', [DashboardWidgetController::class, 'index'])->name('dashboard.widgets.index');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

        // Declarees avant la ressource : `{role}` avalerait le segment `menu`.
        // C'est ici, et nulle part ailleurs, que le menu se regle.
        Route::get('roles/{role}/menu', [RoleMenuController::class, 'index'])->name('roles.menu.index');
        Route::patch('roles/{role}/menu', [RoleMenuController::class, 'update'])->name('roles.menu.update');
        // Un groupe n'ouvre rien : ni route, ni permission. C'est ce qui permet
        // d'en creer, la ou le reste du catalogue reste en code.
        Route::post('roles/{role}/menu/groups', [RoleMenuGroupController::class, 'store'])->name('roles.menu.groups.store');
        Route::delete('roles/{role}/menu/groups/{code}', [RoleMenuGroupController::class, 'destroy'])->name('roles.menu.groups.destroy');
        // Meme precaution que pour le menu, et meme endroit unique de reglage.
        // `PUT` remplace la selection entiere : ce qui n'est pas envoye n'est
        // pas conserve, c'est ainsi qu'on decoche. `DELETE` rend le role aux
        // defauts du catalogue, ce qu'une liste vide ne dirait pas — elle dit
        // « aucun widget ».
        Route::get('roles/{role}/dashboard', [RoleDashboardController::class, 'index'])->name('roles.dashboard.index');
        Route::put('roles/{role}/dashboard', [RoleDashboardController::class, 'update'])->name('roles.dashboard.update');
        Route::delete('roles/{role}/dashboard', [RoleDashboardController::class, 'destroy'])->name('roles.dashboard.destroy');
        Route::apiResource('roles', RoleController::class)->except(['create', 'edit']);
        Route::apiResource('users', UserController::class)->except(['create', 'edit']);
        // Rendre l'acces a un membre : le lien par courriel d'abord, le mot de
        // passe pose pour les comptes qui ne relevent pas de boite. Declarees
        // avant la ressource, sinon `{organizationUser}` avalerait le segment.
        Route::post('organization-users/{organizationUser}/password-reset-link', [MemberPasswordController::class, 'sendLink'])->name('organization-users.password-link');
        Route::put('organization-users/{organizationUser}/password', [MemberPasswordController::class, 'set'])->name('organization-users.password');
        Route::apiResource('organization-users', OrganizationUserController::class)->except(['create', 'edit']);
        Route::apiResource('providers', ProviderController::class)->except(['create', 'edit']);
        Route::apiResource('drivers', DriverController::class)->except(['create', 'edit']);
        Route::apiResource('vehicles', VehicleController::class)->except(['create', 'edit']);
        Route::apiResource('services', ServiceController::class)->except(['create', 'edit']);
        // Les referentiels de type tiennent en deux routes : la source, puis
        // ses valeurs. Un referentiel ajoute par l'organisme est servi sans
        // qu'une ligne soit ecrite ici.
        Route::apiResource('types', TypeController::class)->except(['create', 'edit']);
        Route::apiResource('type-items', TypeItemController::class)
            ->parameters(['type-items' => 'typeItem'])
            ->except(['create', 'edit']);
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
        // Positions du vehicule : le jeton du fournisseur reste au serveur.
        Route::get('orders/{order}/positions', OrderPositionController::class)->name('orders.positions');
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
        // Validation et annulation d'un brouillon : le referentiel dit quels
        // passages existent, l'action les applique dans une transaction.
        // Ce qui attend d'etre planifie : une lecture des commandes, pas une
        // table de plus a tenir a jour.
        Route::get('planning/pool', [PlanningPoolController::class, 'index'])->name('planning.pool');
        Route::post('tours/{tour}/status', [TourStatusController::class, 'changeStatus'])->name('tours.status');
        // Glisser une commande ou des services : un seul appel, une transaction.
        Route::post('tours/{tour}/plan', [TourPlanningController::class, 'plan'])->name('tours.plan');
        Route::post('tours/{tour}/unplan', [TourPlanningController::class, 'unplan'])->name('tours.unplan');
        Route::post('tours/{tour}/reserve', [TourPlanningController::class, 'reserve'])->name('tours.reserve');
        Route::post('tours/{tour}/release', [TourPlanningController::class, 'release'])->name('tours.release');
        Route::get('tours/{tour}/route-geometry', [TourRouteController::class, 'routeGeometry'])->name('tours.route-geometry');
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
        // Les selecteurs de prestations : ce qui reste a facturer chez un
        // client, ce qui reste a regler a un fournisseur. Le serveur decide de
        // l'eligibilite — le §42 refuse que l'ecran en juge seul.
        Route::get('customers/{customer}/billable-services/suggestions', [BillableServiceController::class, 'suggestions'])
            ->name('customers.billable-services.suggestions');
        Route::get('customers/{customer}/billable-services', [BillableServiceController::class, 'index'])
            ->name('customers.billable-services');
        Route::get('providers/{provider}/settleable-services', [SettleableServiceController::class, 'index'])
            ->name('providers.settleable-services');

        // La cloture est une action metier, pas une mutation du CRUD : elle
        // fige la facture et declenche son envoi. Le §24 refuse un `/send`
        // generique — l'envoi suit la cloture, il ne se commande pas.
        // Le recalcul : voir l'ecart, puis l'appliquer. Deux gestes distincts,
        // le §169AM refusant qu'une facture bouge en silence.
        Route::get('invoices/{invoice}/repricing', [InvoiceRepricingController::class, 'show'])
            ->name('invoices.repricing.show');
        Route::post('invoices/{invoice}/reprice', [InvoiceRepricingController::class, 'store'])
            ->name('invoices.reprice');
        Route::get('invoices/{invoice}/document', [InvoiceDocumentController::class, 'show'])->name('invoices.document.show');
        Route::get('invoices/{invoice}/closure', [InvoiceClosureController::class, 'show'])->name('invoices.closure.show');
        Route::post('invoices/{invoice}/close', [InvoiceClosureController::class, 'store'])->name('invoices.close');
        Route::apiResource('invoices', InvoiceController::class)->except(['create', 'edit']);

        // Tarification. La validation de formule precede la ressource :
        // `pricing/formulas/validate` ne doit pas etre lu comme un identifiant
        // de bareme.
        // Le catalogue des variables : lu par tout organisme, ecrit par la
        // seule plateforme. `sources` precede la ressource pour ne pas etre lu
        // comme un identifiant.
        Route::get('pricing-variables/sources', [PricingVariableController::class, 'sources'])
            ->name('pricing-variables.sources');
        Route::apiResource('pricing-variables', PricingVariableController::class)
            ->parameters(['pricing-variables' => 'pricingVariable'])
            ->except(['create', 'edit', 'show']);

        Route::post('pricing/formulas/validate', [FormulaController::class, 'validateFormula'])
            ->name('pricing.formulas.validate');
        // La prefacturation : ce qui reste a facturer, et ce que le bareme
        // donnerait. Le calcul n'y est pas enregistre.
        Route::get('pricing/prebilling', [PrebillingController::class, 'index'])
            ->name('pricing.prebilling');
        Route::apiResource('price-lists', PriceListController::class)
            ->parameters(['price-lists' => 'priceList'])
            ->except(['create', 'edit']);

        // Regles et matrices vivent sous leur bareme : hors de lui, elles ne
        // s'appliquent a personne.
        Route::post('price-lists/{priceList}/rules', [PriceRuleController::class, 'store'])
            ->name('price-lists.rules.store');
        Route::patch('price-rules/{priceRule}', [PriceRuleController::class, 'update'])
            ->name('price-rules.update');
        Route::delete('price-rules/{priceRule}', [PriceRuleController::class, 'destroy'])
            ->name('price-rules.destroy');

        Route::post('price-lists/{priceList}/matrices', [PriceMatrixController::class, 'store'])
            ->name('price-lists.matrices.store');
        Route::patch('price-matrices/{priceMatrix}', [PriceMatrixController::class, 'update'])
            ->name('price-matrices.update');
        Route::delete('price-matrices/{priceMatrix}', [PriceMatrixController::class, 'destroy'])
            ->name('price-matrices.destroy');

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

        // La boite d'envoi de l'organisation. Ressource unique et non liste :
        // une organisation n'a qu'une identite d'expedition, et en autoriser
        // deux poserait la question de savoir laquelle repond.
        Route::get('mail-configuration', [OrganizationMailConfigurationController::class, 'show'])->name('mail-configuration.show');
        Route::put('mail-configuration', [OrganizationMailConfigurationController::class, 'update'])->name('mail-configuration.update');
        Route::delete('mail-configuration', [OrganizationMailConfigurationController::class, 'destroy'])->name('mail-configuration.destroy');
        // L'essai avant la premiere facture : un port ferme ou un mot de passe
        // perime echouent sinon au fond d'une file, sans que personne ne voie
        // rien avant qu'un client ne reclame son courrier.
        Route::post('mail-configuration/test', [OrganizationMailConfigurationController::class, 'test'])->name('mail-configuration.test');

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

        // Eprouver une correspondance sur un vrai fichier, sans rien creer.
        // Precede l'apiResource, sinon `{configuration}/preview` serait lu
        // comme un identifiant.
        Route::post('customer-import-configurations/{configuration}/preview', ImportPreviewController::class)->name('customer-import-configurations.preview');
        // Importer reellement : meme lecture, meme interpreteur, mais les
        // commandes sont ecrites. Tout ou rien, dans une transaction.
        Route::post('customer-import-configurations/{configuration}/import', ImportOrdersController::class)->name('customer-import-configurations.import');
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

        // Modeles : messages et documents, une seule table et une seule API.
        Route::apiResource('templates', TemplateController::class)
            ->except(['create', 'edit']);

        // Communication

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
