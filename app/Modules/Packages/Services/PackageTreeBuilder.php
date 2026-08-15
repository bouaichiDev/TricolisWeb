<?php

declare(strict_types=1);

namespace App\Modules\Packages\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Packages\Models\Package;
use Illuminate\Support\Collection;

/**
 * Reconstruit l'arbre des colis d'une commande.
 *
 * Une seule requête plate est émise, puis l'arbre est assemblé en mémoire :
 * charger les enfants par relation produirait une requête par niveau.
 */
final readonly class PackageTreeBuilder
{
    /**
     * @return Collection<int, Package> les colis racines, enfants attachés
     */
    public function build(Order $order): Collection
    {
        $byParent = $order->packages()->orderBy('created_at')->get()->groupBy('parent_package_id');

        return $this->attachChildren($byParent[null] ?? collect(), $byParent);
    }

    /**
     * @param  Collection<int, Package>  $packages
     * @param  Collection<string, Collection<int, Package>>  $byParent
     * @return Collection<int, Package>
     */
    private function attachChildren(Collection $packages, Collection $byParent): Collection
    {
        return $packages->map(function (Package $package) use ($byParent): Package {
            $package->setRelation('children', $this->attachChildren($byParent[$package->id] ?? collect(), $byParent));

            return $package;
        });
    }
}
