<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\DTOs\CreatePackageData;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Services\OrderScopeGuard;
use App\Modules\Packages\Models\Package;
use App\Modules\Packages\Services\PackageLineAllocator;
use Illuminate\Validation\ValidationException;

/**
 * Crée les colis d'une commande et leurs affectations de lignes.
 *
 * Les colis se référencent entre eux par clé locale au payload : les parents
 * sont donc créés avant leurs enfants, dans l'ordre du tableau reçu.
 */
final readonly class CreateOrderPackages
{
    public function __construct(
        private OrderScopeGuard $guard,
        private PackageLineAllocator $allocator,
    ) {}

    /**
     * @param  list<CreatePackageData>  $packages
     * @param  array<string, OrderLine>  $lines
     * @return array<string, Package> indexé par clé locale et par identifiant
     */
    public function execute(Order $order, array $packages, array $lines): array
    {
        $created = [];

        foreach ($packages as $index => $package) {
            $attributes = $package->attributes;
            $attributes['parent_package_id'] = $this->resolveParent($package, $created, $index);

            if ($package->packageTypeId !== null) {
                $attributes['package_type_id'] = $this->guard
                    ->packageType($package->packageTypeId, $order->organization_id, "packages.$index.packageTypeId")->id;
            }

            if ($package->groupingTypeId !== null) {
                $attributes['grouping_type_id'] = $this->guard
                    ->groupingType($package->groupingTypeId, $order->organization_id, "packages.$index.groupingTypeId")->id;
            }

            $model = $order->packages()->create($attributes);

            $created[(string) $index] = $model;
            $created[$model->id] = $model;

            if ($package->key !== null) {
                $created[$package->key] = $model;
            }

            $this->attachLines($model, $package, $lines, $index);
        }

        return $created;
    }

    /**
     * @param  array<string, Package>  $created
     */
    private function resolveParent(CreatePackageData $package, array $created, int $index): ?string
    {
        if ($package->parentPackageId !== null) {
            return $package->parentPackageId;
        }

        if ($package->parentKey === null) {
            return null;
        }

        if (! isset($created[$package->parentKey])) {
            throw ValidationException::withMessages([
                "packages.$index.parentKey" => ['Le colis parent doit être déclaré avant son enfant dans le payload.'],
            ]);
        }

        return $created[$package->parentKey]->id;
    }

    /**
     * @param  array<string, OrderLine>  $lines
     */
    private function attachLines(Package $package, CreatePackageData $data, array $lines, int $index): void
    {
        foreach ($data->lines as $position => $line) {
            $key = $line['lineKey'] ?? $line['orderLineId'];

            if ($key === null || ! isset($lines[$key])) {
                throw ValidationException::withMessages([
                    "packages.$index.lines.$position.lineKey" => ['Cette ligne de commande est introuvable dans le payload.'],
                ]);
            }

            $this->allocator->allocate($package, $lines[$key], (float) $line['quantity'], "packages.$index.lines.$position.quantity");
        }
    }
}
