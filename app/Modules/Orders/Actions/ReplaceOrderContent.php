<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\DTOs\CreateOrderLineData;
use App\Modules\Orders\DTOs\CreateOrderServiceData;
use App\Modules\Orders\DTOs\CreatePackageData;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderContentEngagement;
use App\Modules\Planning\Actions\GeocodeMissingAddresses;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Réécrit une commande — en-tête, lignes, colis, affectations et services —
 * d'un bloc, avec les **mêmes** briques que la création (`CreateOrderLines`,
 * `CreateOrderPackages`, `CreateOrderServices`, `RecalculateOrderTotals`).
 *
 * Réservé à une commande dont le contenu n'est pas engagé : rien en aval
 * (tournée, facture, stock réservé…) ne s'y appuie encore. C'est ce qui rend
 * le remplacement sûr, et l'action le revérifie elle-même, sous transaction,
 * plutôt que de s'en remettre à l'appelant.
 */
final readonly class ReplaceOrderContent
{
    public function __construct(
        private OrderContentEngagement $engagement,
        private CreateOrderLines $lines,
        private CreateOrderPackages $packages,
        private CreateOrderServices $services,
        private RecalculateOrderTotals $totals,
        private GeocodeMissingAddresses $geocode,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $header  attributs de colonnes (`customer_reference`…)
     * @param  list<array<string, mixed>>  $lines  entrées de `CreateOrderLineData::fromArray`
     * @param  list<array<string, mixed>>  $packages
     * @param  list<array<string, mixed>>  $services
     */
    public function execute(Order $order, array $header, array $lines, array $packages, array $services, User $user, ?string $ipAddress = null): Order
    {
        return DB::transaction(function () use ($order, $header, $lines, $packages, $services, $user, $ipAddress): Order {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($this->engagement->engaged([$order->id]) !== []) {
                throw new RuntimeException('Le contenu de cette commande est engagé : il ne peut plus être réécrit.');
            }

            $before = $this->snapshot($order);

            $order->orderServices()->delete();
            $order->packages()->delete();
            $order->lines()->delete();

            $order->forceFill([...$header, 'updated_by' => $user->id])->save();

            $createdLines = $this->lines->execute($order, $order->customer, array_map(CreateOrderLineData::fromArray(...), $lines));
            $createdPackages = $this->packages->execute($order, array_map(CreatePackageData::fromArray(...), $packages), $createdLines);
            $createdServices = $this->services->execute($order, array_map(CreateOrderServiceData::fromArray(...), $services), $createdPackages);

            $this->totals->execute($order);
            $this->geocode->execute(collect($createdServices)->pluck('address_id')->all(), $order->organization_id);

            $this->audit->execute($order->organization_id, $user, 'updated', $order, $before, $this->snapshot($order->fresh()), null, $ipAddress);

            return $order->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Order $order): array
    {
        return [
            ...$order->only(['customer_reference', 'external_reference', 'order_date', 'worker_remark', 'weight', 'volume', 'package_count']),
            'line_count' => $order->lines()->count(),
            'service_count' => $order->orderServices()->count(),
        ];
    }
}
