<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\DTOs\CreateStockMovementData;
use App\Modules\Stock\Enums\StockAdjustmentRequestStatus;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockLocation;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Services\StockAdjustmentSettings;
use App\Modules\Stock\Services\StockScopeGuard;
use App\Shared\Database\MorphMap;
use App\Shared\Support\AuditContext;
use App\Shared\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Accepte une demande de correction : le stock est ramené à la quantité
 * constatée par le client.
 *
 * **Le mouvement corrige vers la quantité constatée, pas de l'écart déclaré.**
 * Si le stock a bougé depuis la demande, c'est l'état actuel qui est corrigé :
 *
 * ```text
 * delta = requested_quantity − quantité actuelle (lue sous verrou : la balance
 *         de l'emplacement, ou le total de l'article pour « tout l'article »)
 * delta > 0 → entrée   (destination = emplacement, quantité = delta)
 * delta < 0 → sortie   (source = emplacement, quantité = |delta|)
 * delta = 0 → aucun mouvement, la demande est acceptée telle quelle
 * ```
 *
 * Aucune écriture de balance ici : tout passe par `CreateStockMovementAction`,
 * qui refuse d'elle-même de descendre sous zéro ou sous le réservé (409).
 *
 * La demande est relue sous verrou : deux validations concurrentes ne peuvent
 * pas créer deux mouvements, la seconde reçoit `adjustmentAlreadyReviewed`.
 */
final readonly class ApproveStockAdjustmentRequestAction
{
    public function __construct(
        private CreateStockMovementAction $movements,
        private StockAdjustmentSettings $settings,
        private StockScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(StockAdjustmentRequest $request, ?string $locationId, ?string $reviewComment, AuditContext $context, string $now): StockAdjustmentRequest
    {
        return DB::transaction(function () use ($request, $locationId, $reviewComment, $context, $now): StockAdjustmentRequest {
            $locked = StockAdjustmentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw StockConflict::adjustmentAlreadyReviewed();
            }

            $location = $this->location($locked, $locationId, $context->organizationId);
            $delta = Money::round(Money::subtract((string) $locked->requested_quantity, $this->lockedQuantity($locked, $location)), 3);
            $movement = bccomp($delta, '0', 3) === 0 ? null : $this->move($locked, $location, $delta, $context, $now);

            $locked->update([
                'status' => StockAdjustmentRequestStatus::APPROVED,
                'stock_location_id' => $location->id,
                'reviewed_by' => $context->user?->id,
                'reviewed_at' => $now,
                'review_comment' => $reviewComment,
                'stock_movement_id' => $movement?->id,
            ]);

            $this->audit->execute($context->organizationId, $context->user, 'stock_adjustment_request.approved', $locked,
                ['status' => StockAdjustmentRequestStatus::PENDING->value],
                ['status' => StockAdjustmentRequestStatus::APPROVED->value, 'stock_location_id' => $location->id, 'stock_movement_id' => $movement?->id, 'delta' => $delta],
                null, $context->ipAddress);

            return $locked;
        });
    }

    /**
     * L'emplacement à corriger : celui de la demande, ou — pour une demande
     * « tout l'article » — celui que l'interne choisit. Jamais les deux.
     */
    private function location(StockAdjustmentRequest $request, ?string $locationId, string $organizationId): StockLocation
    {
        if ($request->stock_location_id !== null) {
            if ($locationId !== null) {
                throw ValidationException::withMessages(['stockLocationId' => ['Cette demande porte déjà sur un emplacement : il ne se choisit pas.']]);
            }

            return $request->stockLocation()->firstOrFail();
        }

        if ($locationId === null) {
            throw ValidationException::withMessages(['stockLocationId' => ['Choisissez l’emplacement à corriger : la demande porte sur tout l’article.']]);
        }

        return $this->guard->stockLocation($locationId, $organizationId);
    }

    /**
     * Quantité actuelle, lue sous verrou : rien ne peut bouger avant le mouvement.
     *
     * Même périmètre que `displayed_quantity` : la balance de l'emplacement,
     * ou — demande « tout l'article » — le **total** de l'article, puisque
     * c'est un total que le client a constaté. L'écart est ensuite porté par
     * l'emplacement choisi, qui doit avoir une balance de l'article.
     */
    private function lockedQuantity(StockAdjustmentRequest $request, StockLocation $location): string
    {
        $balances = StockBalance::where('stock_item_id', $request->stock_item_id)
            ->when($request->stock_location_id !== null, static fn ($query) => $query->where('stock_location_id', $location->id))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if (! $balances->contains('stock_location_id', $location->id)) {
            throw ValidationException::withMessages(['stockLocationId' => ['Cet article n’a pas de stock à cet emplacement.']]);
        }

        // Pas `Money::sum` : elle arrondit à deux décimales, une quantité en a trois.
        return Money::round($balances->reduce(static fn (string $total, StockBalance $balance): string => Money::add($total, (string) $balance->quantity), '0'), 3);
    }

    private function move(StockAdjustmentRequest $request, StockLocation $location, string $delta, AuditContext $context, string $now): StockMovement
    {
        $inbound = bccomp($delta, '0', 3) > 0;

        return $this->movements->execute(new CreateStockMovementData(
            stockItemId: $request->stock_item_id,
            movementType: $this->settings->movementType($context->organizationId),
            quantity: ltrim($delta, '-'),
            sourceLocationId: $inbound ? null : $location->id,
            destinationLocationId: $inbound ? $location->id : null,
            sourceEntityType: MorphMap::STOCK_ADJUSTMENT_REQUEST,
            sourceEntityId: $request->id,
        ), $context, $now);
    }
}
