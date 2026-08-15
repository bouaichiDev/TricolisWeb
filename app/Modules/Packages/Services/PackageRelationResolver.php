<?php

declare(strict_types=1);

namespace App\Modules\Packages\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderScopeGuard;
use App\Modules\Packages\Models\Package;

/**
 * Résout et valide les relations d'un colis : type, regroupement et parent.
 *
 * Les trois passent par les mêmes contrôles à la création comme à la
 * modification — déplacer un colis dans la hiérarchie est aussi risqué que l'y
 * placer d'emblée.
 */
final readonly class PackageRelationResolver
{
    public function __construct(
        private OrderScopeGuard $scope,
        private PackageHierarchyGuard $hierarchy,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function apply(Package $package, array $data, Order $order, string $organizationId): void
    {
        if (array_key_exists('packageTypeId', $data)) {
            $package->package_type_id = $data['packageTypeId'] === null
                ? null
                : $this->scope->packageType($data['packageTypeId'], $organizationId)->id;
        }

        if (array_key_exists('groupingTypeId', $data)) {
            $package->grouping_type_id = $data['groupingTypeId'] === null
                ? null
                : $this->scope->groupingType($data['groupingTypeId'], $organizationId)->id;
        }

        if (! array_key_exists('parentPackageId', $data)) {
            return;
        }

        $parent = $data['parentPackageId'] === null ? null : $order->packages()->find($data['parentPackageId']);

        // Un parent d'une autre commande est traité comme inexistant : le 404
        // ne révèle pas qu'un colis portant cet identifiant existe ailleurs.
        abort_if($data['parentPackageId'] !== null && $parent === null, 404, 'Colis parent introuvable pour cette commande.');

        $this->hierarchy->assertValidParent($package, $parent);
        $package->parent_package_id = $parent?->id;
    }
}
