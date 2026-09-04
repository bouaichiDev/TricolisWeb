<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Identity\Models\Role;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Providers\Models\Provider;

/**
 * Les compteurs d'administration.
 *
 * Ce sont les quatre cartes que le tableau de bord affichait en dur, plus trois
 * autres. Elles ne se comptent plus en demandant une page d'un élément à quatre
 * listes paginées pour n'en lire que `meta.total` : c'était le seul chiffre
 * qu'offrait le backend, et cela coûtait quatre requêtes HTTP, quatre
 * autorisations et quatre pagination complètes pour quatre entiers.
 *
 * `users_count` compte les **appartenances**, pas les comptes. Un même compte
 * peut travailler dans deux organisations ; le compter dans les deux serait
 * juste, mais la carte annonce « les utilisateurs de cette organisation », et
 * c'est l'appartenance qui les définit.
 */
final readonly class AdministrationData implements DashboardDataSource
{
    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function resolve(array $keys, DashboardContext $context): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->resolveOne($key, $context);
        }

        return $data;
    }

    private function resolveOne(string $key, DashboardContext $context): mixed
    {
        $organizationId = $context->organizationId;

        return match ($key) {
            'customers_count' => DashboardPayload::kpi(
                Customer::query()->where('organization_id', $organizationId)->count()
            ),
            'agencies_count' => DashboardPayload::kpi(
                Agency::query()->where('organization_id', $organizationId)->count()
            ),
            'users_count' => DashboardPayload::kpi(
                OrganizationUser::query()->where('organization_id', $organizationId)->count()
            ),

            // Les rôles de portée plateforme n'ont pas d'organisation : le
            // filtre les écarte de lui-même, et la carte compte donc ce que la
            // liste des rôles montre.
            'roles_count' => DashboardPayload::kpi(
                Role::query()->where('organization_id', $organizationId)->count()
            ),

            'providers_count' => DashboardPayload::kpi(
                Provider::query()->where('organization_id', $organizationId)->count()
            ),
            'drivers_count' => DashboardPayload::kpi(
                Driver::query()->where('organization_id', $organizationId)->count()
            ),

            // Le véhicule porte désormais son organisation en colonne : son
            // scope le dit, et le déduire du fournisseur laisserait de côté
            // ceux du transporteur, qui n'en ont pas.
            'vehicles_count' => DashboardPayload::kpi(
                Vehicle::query()->inOrganization($organizationId)->count()
            ),

            default => null,
        };
    }
}
