<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Packages;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Packages\StorePackageLineRequest;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Packages\Models\Package;
use App\Modules\Packages\Models\PackageOrderLine;
use App\Modules\Packages\Services\PackageLineAllocator;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Répartition des lignes de commande dans les colis.
 *
 * Une ligne peut être éclatée entre plusieurs colis ; la somme des quantités
 * affectées ne peut pas dépasser la quantité commandée.
 */
class PackageLineController extends Controller
{
    use ResolvesOrderScope;

    public function __construct(private readonly PackageLineAllocator $allocator) {}

    /**
     * Affecter une ligne à un colis.
     *
     * Permission requise : `packages.update`. La ligne doit appartenir à la même
     * commande. La quantité totale affectée est vérifiée sous verrou, ce qui
     * rend l'opération sûre en concurrence.
     */
    public function store(StorePackageLineRequest $request, Order $order, Package $package): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $package, 'Colis');
        $this->authorize('managePackages', [$order, 'update']);
        $this->assertOrderIsEditable($order);

        $data = $request->validated();
        $line = $this->findLine($order, $data['orderLineId']);

        $allocation = $this->allocator->allocate($package, $line, (float) $data['quantity']);
        $this->audit($request, $organizationId, 'package_line_assigned', $allocation, null, $allocation->toArray());

        return ApiResponse::created($this->present($allocation));
    }

    /**
     * Modifier la quantité affectée.
     *
     * Permission requise : `packages.update`.
     */
    public function update(StorePackageLineRequest $request, Order $order, Package $package, string $line): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $package, 'Colis');
        $this->authorize('managePackages', [$order, 'update']);
        $this->assertOrderIsEditable($order);

        $allocation = $this->findAllocation($package, $line);
        $old = $allocation->toArray();

        $data = $request->validated();
        $orderLine = $this->findLine($order, $data['orderLineId']);
        $updated = $this->allocator->allocate($package, $orderLine, (float) $data['quantity']);

        $this->audit($request, $organizationId, 'package_line_updated', $updated, $old, $updated->fresh()->toArray());

        return ApiResponse::ok($this->present($updated->fresh()));
    }

    /**
     * Retirer une ligne d'un colis.
     *
     * Permission requise : `packages.update`.
     *
     * @response 204
     */
    public function destroy(Request $request, Order $order, Package $package, string $line): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $package, 'Colis');
        $this->authorize('managePackages', [$order, 'update']);
        $this->assertOrderIsEditable($order);

        $allocation = $this->findAllocation($package, $line);
        $this->audit($request, $organizationId, 'package_line_released', $allocation, $allocation->toArray(), null);
        $this->allocator->release($allocation);

        return ApiResponse::noContent();
    }

    private function findLine(Order $order, string $lineId): OrderLine
    {
        $line = $order->lines()->find($lineId);

        abort_if($line === null, 404, 'Ligne introuvable pour cette commande.');

        return $line;
    }

    private function findAllocation(Package $package, string $id): PackageOrderLine
    {
        $allocation = PackageOrderLine::where('package_id', $package->id)
            ->where(fn ($query) => $query->whereKey($id)->orWhere('order_line_id', $id))
            ->first();

        abort_if($allocation === null, 404, 'Affectation introuvable pour ce colis.');

        return $allocation;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PackageOrderLine $allocation): array
    {
        return [
            'id' => $allocation->id,
            'packageId' => $allocation->package_id,
            'orderLineId' => $allocation->order_line_id,
            'quantity' => $allocation->quantity,
        ];
    }
}
