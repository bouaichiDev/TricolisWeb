<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CustomerPortal\Actions\ProvisionCustomerPortalRoles;
use App\Modules\CustomerPortal\Support\CustomerPortalPermissions;
use App\Modules\Identity\Models\Permission;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Enums\MenuSection;
use Illuminate\Database\Seeder;

/**
 * Permissions `customer.*`, rôles `CUSTOMER_*` et leurs affectations.
 *
 * Rejouable sans doublon : les permissions sont retrouvées par code, les rôles
 * par `(organisation, code)`. Il ne touche ni aux permissions ni aux rôles
 * internes — il n'écrit que des lignes dont le code commence par `customer.`
 * ou `CUSTOMER_`.
 */
class CustomerPortalSeeder extends Seeder
{
    public function run(ProvisionCustomerPortalRoles $provision): void
    {
        $new = [];

        foreach (CustomerPortalPermissions::definitions() as $definition) {
            $permission = Permission::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'module' => $definition['module'],
                    'action' => $definition['action'],
                    'menu_section' => MenuSection::CUSTOMER_PORTAL->value,
                ],
            );

            if ($permission->wasRecentlyCreated) {
                $new[] = $permission->code;
            }
        }

        Organization::each(static fn (Organization $organization) => $provision->execute($organization->id, $new));
    }
}
