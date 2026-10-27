<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Permission;
use App\Modules\Organizations\Models\Organization;
use App\Modules\ProviderPortal\Actions\ProvisionProviderPortalRoles;
use App\Modules\ProviderPortal\Support\ProviderPortalPermissions;
use App\Shared\Enums\MenuSection;
use Illuminate\Database\Seeder;

/**
 * Permissions `provider.*`, rôles `PROVIDER_*` et leurs affectations.
 *
 * Rejouable sans doublon, et à rejouer en production à chaque phase qui
 * ajoute des permissions : il n'écrit que des lignes dont le code commence
 * par `provider.` ou `PROVIDER_`.
 */
class ProviderPortalSeeder extends Seeder
{
    public function run(ProvisionProviderPortalRoles $provision): void
    {
        $new = [];

        foreach (ProviderPortalPermissions::definitions() as $definition) {
            $permission = Permission::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'module' => $definition['module'],
                    'action' => $definition['action'],
                    'menu_section' => MenuSection::PROVIDER_PORTAL->value,
                ],
            );

            if ($permission->wasRecentlyCreated) {
                $new[] = $permission->code;
            }
        }

        Organization::each(static fn (Organization $organization) => $provision->execute($organization->id, $new));
    }
}
