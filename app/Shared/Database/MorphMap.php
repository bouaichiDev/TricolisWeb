<?php

declare(strict_types=1);

namespace App\Shared\Database;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Models\InvoiceLineAddressSnapshot;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Claims\Models\Claim;
use App\Modules\Communications\Models\CommunicationAttachment;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Contacts\Models\AddressContact;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerSite;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLink;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\OrderServiceContact;
use App\Modules\Orders\Models\OrderServicePackage;
use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Organizations\Models\Subscription;
use App\Modules\Packages\Models\GroupingType;
use App\Modules\Packages\Models\Package;
use App\Modules\Packages\Models\PackageOrderLine;
use App\Modules\Packages\Models\PackageType;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Providers\Models\Provider;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use App\Modules\Statuses\Models\Status;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Models\StockReservation;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use App\Modules\Tracking\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Valeurs mÃ©tier stables utilisÃ©es pour les relations polymorphes.
 *
 * Aucun nom de classe PHP n'est stockÃ© en base. Seules les entitÃ©s rÃ©ellement
 * livrÃ©es y figurent : les alias des modules futurs (fournisseurs, chauffeurs,
 * vÃ©hicules, rÃ©clamations, factures) seront ajoutÃ©s avec leur module.
 */
final class MorphMap
{
    public const string STATUS = 'status';

    public const string ORGANIZATION = 'organization';

    public const string USER = 'user';

    public const string ORGANIZATION_USER = 'organization_user';

    public const string SUBSCRIPTION = 'subscription';

    public const string ROLE = 'role';

    public const string AGENCY = 'agency';

    public const string ADDRESS = 'address';

    public const string CONTACT = 'contact';

    public const string DEPOT = 'depot';

    public const string CUSTOMER = 'customer';

    public const string CUSTOMER_SITE = 'customer_site';

    public const string ORDER = 'order';

    public const string DOCUMENT = 'document';

    public const string ORDER_LINE = 'order_line';

    public const string ORDER_SERVICE = 'order_service';

    public const string ORDER_SERVICE_CONTACT = 'order_service_contact';

    public const string ORDER_SERVICE_PACKAGE = 'order_service_package';

    public const string SERVICE = 'service';

    public const string CUSTOMER_CATALOG = 'customer_catalog';

    public const string CUSTOMER_CATALOG_ITEM = 'customer_catalog_item';

    public const string PACKAGE = 'package';

    public const string PACKAGE_TYPE = 'package_type';

    public const string GROUPING_TYPE = 'grouping_type';

    public const string PACKAGE_ORDER_LINE = 'package_order_line';

    public const string PROVIDER = 'provider';

    public const string DRIVER = 'driver';

    public const string VEHICLE_TYPE = 'vehicle_type';

    public const string VEHICLE = 'vehicle';

    public const string ENTITY_ADDRESS = 'entity_address';

    public const string ENTITY_CONTACT = 'entity_contact';

    public const string ADDRESS_CONTACT = 'address_contact';

    public const string DOCUMENT_LINK = 'document_link';

    public const string TOUR = 'tour';

    public const string TOUR_STOP = 'tour_stop';

    public const string TOUR_STOP_SERVICE = 'tour_stop_service';

    public const string TOUR_PERIOD = 'tour_period';

    public const string TOUR_PERIOD_ASSIGNMENT = 'tour_period_assignment';

    public const string TRACKING_EVENT = 'tracking_event';

    public const string PROOF_OF_DELIVERY = 'proof_of_delivery';

    public const string CLAIM = 'claim';

    public const string INVOICE = 'invoice';

    public const string INVOICE_LINE = 'invoice_line';

    public const string INVOICE_LINE_ADDRESS_SNAPSHOT = 'invoice_line_address_snapshot';

    public const string PROVIDER_SETTLEMENT = 'provider_settlement';

    public const string PROVIDER_SETTLEMENT_LINE = 'provider_settlement_line';

    public const string STOCK_ITEM = 'stock_item';

    public const string STOCK_LOCATION = 'stock_location';

    public const string STOCK_BALANCE = 'stock_balance';

    public const string STOCK_MOVEMENT = 'stock_movement';

    public const string STOCK_RESERVATION = 'stock_reservation';

    public const string CUSTOMER_IMPORT_CONFIGURATION = 'customer_import_configuration';

    public const string CUSTOMER_API_CONFIGURATION = 'customer_api_configuration';

    public const string CUSTOMER_EXPORT_CONFIGURATION = 'customer_export_configuration';

    public const string EXPORT_JOB = 'export_job';

    public const string COMMUNICATION_TEMPLATE = 'communication_template';

    public const string COMMUNICATION_RULE = 'communication_rule';

    public const string ORDER_COMMUNICATION = 'order_communication';

    public const string COMMUNICATION_ATTACHMENT = 'communication_attachment';

    /**
     * Enregistre la morph map auprÃ¨s d'Eloquent.
     *
     * Les tables de liaison y figurent aussi : elles ne portent pas de relation
     * polymorphe, mais elles sont auditÃ©es, et `AuditLog.entity_type` ne doit
     * jamais contenir un nom de classe PHP.
     */
    public static function register(): void
    {
        Relation::morphMap([
            self::STATUS => Status::class,
            self::ORGANIZATION => Organization::class,
            self::USER => User::class,
            self::ORGANIZATION_USER => OrganizationUser::class,
            self::SUBSCRIPTION => Subscription::class,
            self::ROLE => Role::class,
            self::AGENCY => Agency::class,
            self::ADDRESS => Address::class,
            self::CONTACT => Contact::class,
            self::DEPOT => Depot::class,
            self::CUSTOMER => Customer::class,
            self::CUSTOMER_SITE => CustomerSite::class,
            self::DOCUMENT => Document::class,
            self::ORDER => Order::class,
            self::ORDER_LINE => OrderLine::class,
            self::ORDER_SERVICE => OrderService::class,
            self::ORDER_SERVICE_CONTACT => OrderServiceContact::class,
            self::ORDER_SERVICE_PACKAGE => OrderServicePackage::class,
            self::SERVICE => Service::class,
            self::CUSTOMER_CATALOG => CustomerCatalog::class,
            self::CUSTOMER_CATALOG_ITEM => CustomerCatalogItem::class,
            self::PACKAGE => Package::class,
            self::PACKAGE_TYPE => PackageType::class,
            self::GROUPING_TYPE => GroupingType::class,
            self::PACKAGE_ORDER_LINE => PackageOrderLine::class,
            self::PROVIDER => Provider::class,
            self::DRIVER => Driver::class,
            self::VEHICLE_TYPE => VehicleType::class,
            self::VEHICLE => Vehicle::class,
            self::ENTITY_ADDRESS => EntityAddress::class,
            self::ENTITY_CONTACT => EntityContact::class,
            self::ADDRESS_CONTACT => AddressContact::class,
            self::DOCUMENT_LINK => DocumentLink::class,
            self::TOUR => Tour::class,
            self::TOUR_STOP => TourStop::class,
            self::TOUR_STOP_SERVICE => TourStopService::class,
            self::TOUR_PERIOD => TourPeriod::class,
            self::TOUR_PERIOD_ASSIGNMENT => TourPeriodAssignment::class,
            self::TRACKING_EVENT => TrackingEvent::class,
            self::PROOF_OF_DELIVERY => ProofOfDelivery::class,
            self::CLAIM => Claim::class,
            self::INVOICE => Invoice::class,
            self::INVOICE_LINE => InvoiceLine::class,
            self::INVOICE_LINE_ADDRESS_SNAPSHOT => InvoiceLineAddressSnapshot::class,
            self::PROVIDER_SETTLEMENT => ProviderSettlement::class,
            self::PROVIDER_SETTLEMENT_LINE => ProviderSettlementLine::class,
            self::STOCK_ITEM => StockItem::class,
            self::STOCK_LOCATION => StockLocation::class,
            self::STOCK_BALANCE => StockBalance::class,
            self::STOCK_MOVEMENT => StockMovement::class,
            self::STOCK_RESERVATION => StockReservation::class,
            self::CUSTOMER_IMPORT_CONFIGURATION => CustomerImportConfiguration::class,
            self::CUSTOMER_API_CONFIGURATION => CustomerApiConfiguration::class,
            self::CUSTOMER_EXPORT_CONFIGURATION => CustomerExportConfiguration::class,
            self::EXPORT_JOB => ExportJob::class,
            self::COMMUNICATION_TEMPLATE => CommunicationTemplate::class,
            self::COMMUNICATION_RULE => CommunicationRule::class,
            self::ORDER_COMMUNICATION => OrderCommunication::class,
            self::COMMUNICATION_ATTACHMENT => CommunicationAttachment::class,
        ]);
    }

    /**
     * Alias mÃ©tier connus, indexÃ©s par alias.
     *
     * Sert notamment Ã  valider `StockMovement.sourceEntityType` : la liste des
     * types autorisÃ©s est **dÃ©rivÃ©e** de la morph map, jamais recopiÃ©e â€” une
     * copie divergerait au premier module ajoutÃ©.
     *
     * @return array<string, class-string>
     */
    public static function registered(): array
    {
        /** @var array<string, class-string> $map */
        $map = Relation::morphMap();

        return $map;
    }

    /**
     * Retourne la classe Eloquent associÃ©e Ã  un alias morphique.
     *
     * @return class-string|null
     */
    public static function class(string $alias): ?string
    {
        /** @var array<string, class-string>|null $map */
        $map = Relation::morphMap();

        return $map[$alias] ?? null;
    }
}
