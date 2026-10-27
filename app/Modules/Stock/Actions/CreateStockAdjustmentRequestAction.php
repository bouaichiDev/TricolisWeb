<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\Enums\StockAdjustmentRequestStatus;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Shared\Support\AuditContext;
use App\Shared\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dépose une demande de correction de stock — **sans toucher au stock**.
 *
 * La quantité affichée est lue ici, jamais reçue du client : la balance de
 * l'article à l'emplacement désigné, ou la somme de ses balances. Elle est
 * figée dans la demande, pour que le transporteur lise l'écart tel qu'il a
 * été constaté.
 *
 * Refus : un emplacement où l'article n'a pas de balance (422), une quantité
 * identique à celle affichée — il n'y a pas d'écart (422), une demande déjà
 * en attente pour le même article et le même emplacement (409).
 */
final readonly class CreateStockAdjustmentRequestAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(StockItem $item, ?StockLocation $location, string $requestedQuantity, string $reason, ?string $comment, AuditContext $context): StockAdjustmentRequest
    {
        $displayed = $this->displayed($item, $location);
        $requested = Money::round($requestedQuantity, 3);

        if ($displayed === $requested) {
            throw ValidationException::withMessages(['requestedQuantity' => ['La quantité constatée est celle affichée : il n’y a pas d’écart à corriger.']]);
        }

        return DB::transaction(function () use ($item, $location, $displayed, $requested, $reason, $comment, $context): StockAdjustmentRequest {
            $pending = StockAdjustmentRequest::query()
                ->where('stock_item_id', $item->id)
                ->where('stock_location_id', $location?->id)
                ->where('status', StockAdjustmentRequestStatus::PENDING->value)
                ->lockForUpdate()
                ->exists();

            if ($pending) {
                throw StockConflict::adjustmentAlreadyPending();
            }

            $request = StockAdjustmentRequest::create([
                'organization_id' => $context->organizationId,
                'customer_id' => $item->customer_id,
                'stock_item_id' => $item->id,
                'stock_location_id' => $location?->id,
                'displayed_quantity' => $displayed,
                'requested_quantity' => $requested,
                'reason' => $reason,
                'comment' => $comment,
                'status' => StockAdjustmentRequestStatus::PENDING,
                'requested_by' => $context->user?->id,
            ]);

            $this->audit->execute($context->organizationId, $context->user, 'stock_adjustment_request.created', $request, null,
                $request->only(['stock_item_id', 'stock_location_id', 'displayed_quantity', 'requested_quantity', 'status']), null, $context->ipAddress);

            return $request;
        });
    }

    private function displayed(StockItem $item, ?StockLocation $location): string
    {
        if ($location === null) {
            // Somme SQL brute : `sum()` d'Eloquent la rendrait en flottant.
            $total = StockBalance::where('stock_item_id', $item->id)->toBase()->selectRaw('SUM(quantity) AS total')->value('total');

            return Money::round((string) ($total ?? '0'), 3);
        }

        $balance = StockBalance::where('stock_item_id', $item->id)->where('stock_location_id', $location->id)->first();

        if ($balance === null) {
            throw ValidationException::withMessages(['stockLocationId' => ['Cet article n’a pas de stock à cet emplacement.']]);
        }

        return Money::round((string) $balance->quantity, 3);
    }
}
