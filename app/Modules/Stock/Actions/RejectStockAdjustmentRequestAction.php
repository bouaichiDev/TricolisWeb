<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\Enums\StockAdjustmentRequestStatus;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Refuse une demande de correction — **aucune écriture de stock**.
 *
 * La réponse est obligatoire (contrôlée par la requête) : le client doit
 * savoir pourquoi. Même verrou et même garde que l'acceptation : une demande
 * tranchée ne se rejoue pas, ni dans un sens ni dans l'autre (409).
 */
final readonly class RejectStockAdjustmentRequestAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(StockAdjustmentRequest $request, string $reviewComment, AuditContext $context, string $now): StockAdjustmentRequest
    {
        return DB::transaction(function () use ($request, $reviewComment, $context, $now): StockAdjustmentRequest {
            $locked = StockAdjustmentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw StockConflict::adjustmentAlreadyReviewed();
            }

            $locked->update([
                'status' => StockAdjustmentRequestStatus::REJECTED,
                'reviewed_by' => $context->user?->id,
                'reviewed_at' => $now,
                'review_comment' => $reviewComment,
            ]);

            $this->audit->execute($context->organizationId, $context->user, 'stock_adjustment_request.rejected', $locked,
                ['status' => StockAdjustmentRequestStatus::PENDING->value],
                ['status' => StockAdjustmentRequestStatus::REJECTED->value],
                null, $context->ipAddress);

            return $locked;
        });
    }
}
