<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerSite;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Organizations\Models\Subscription;
use App\Modules\Packages\Models\GroupingType;
use App\Modules\Packages\Models\PackageType;
use App\Policies\AddressPolicy;
use App\Policies\AgencyPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ContactPolicy;
use App\Policies\CustomerCatalogPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\CustomerSitePolicy;
use App\Policies\DepotPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EntityAddressPolicy;
use App\Policies\EntityContactPolicy;
use App\Policies\OrderPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\OrganizationUserPolicy;
use App\Policies\PackageReferentialPolicy;
use App\Policies\RolePolicy;
use App\Policies\ServicePolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\LogFile;
use Opcodes\LogViewer\LogFolder;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Organization::class => OrganizationPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        User::class => UserPolicy::class,
        Agency::class => AgencyPolicy::class,
        Depot::class => DepotPolicy::class,
        Customer::class => CustomerPolicy::class,
        CustomerSite::class => CustomerSitePolicy::class,
        Address::class => AddressPolicy::class,
        EntityAddress::class => EntityAddressPolicy::class,
        Contact::class => ContactPolicy::class,
        EntityContact::class => EntityContactPolicy::class,
        Order::class => OrderPolicy::class,
        Service::class => ServicePolicy::class,
        CustomerCatalog::class => CustomerCatalogPolicy::class,
        PackageType::class => PackageReferentialPolicy::class,
        GroupingType::class => PackageReferentialPolicy::class,
        Document::class => DocumentPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        OrganizationUser::class => OrganizationUserPolicy::class,
        Role::class => RolePolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::define('viewLogViewer', static function (?User $user): bool {
            if ($user === null) {
                return false;
            }

            $allowedEmails = collect(config('log-viewer.allowed_emails', []));

            return $allowedEmails->contains(strtolower($user->email));
        });

        Gate::define('downloadLogFile', static fn (User $user, LogFile $file): bool => Gate::allows('viewLogViewer'));
        Gate::define('downloadLogFolder', static fn (User $user, LogFolder $folder): bool => Gate::allows('viewLogViewer'));
        Gate::define('deleteLogFile', static fn (User $user, LogFile $file): bool => false);
        Gate::define('deleteLogFolder', static fn (User $user, LogFolder $folder): bool => false);
    }
}
