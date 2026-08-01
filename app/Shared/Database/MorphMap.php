<?php

declare(strict_types=1);

namespace App\Shared\Database;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Contacts\Models\AddressContact;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerSite;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLink;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
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
use App\Modules\Providers\Models\Provider;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Valeurs métier stables utilisées pour les relations polymorphes.
 *
 * Aucun nom de classe PHP n'est stocké en base. Seules les entités réellement
 * livrées y figurent : les alias des modules futurs (fournisseurs, chauffeurs,
 * véhicules, réclamations, factures) seront ajoutés avec leur module.
 */
final class MorphMap
{
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

    /**
     * Enregistre la morph map auprès d'Eloquent.
     *
     * Les tables de liaison y figurent aussi : elles ne portent pas de relation
     * polymorphe, mais elles sont auditées, et `AuditLog.entity_type` ne doit
     * jamais contenir un nom de classe PHP.
     */
    public static function register(): void
    {
        Relation::morphMap([
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
        ]);
    }

    /**
     * Retourne la classe Eloquent associée à un alias morphique.
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
