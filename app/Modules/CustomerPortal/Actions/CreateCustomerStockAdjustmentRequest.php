<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\Stock\CustomerStockScope;
use App\Modules\Stock\Actions\CreateStockAdjustmentRequestAction;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockLocation;
use App\Shared\Support\AuditContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Demande de correction déposée depuis le portail (§19).
 *
 * L'article est cherché dans le stock du client (404 sinon) ; l'emplacement,
 * parmi ceux où cet article a une balance. La demande elle-même passe par
 * l'Action du module Stock, qui lit la quantité affichée et **ne touche pas
 * au stock**.
 */
final readonly class CreateCustomerStockAdjustmentRequest
{
    public function __construct(
        private CurrentCustomerContext $context,
        private CustomerStockScope $scope,
        private CreateStockAdjustmentRequestAction $create,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(array $input, Request $request): StockAdjustmentRequest
    {
        $item = $this->scope->find($this->scope->items(), (string) $input['stockItemId']);
        $location = null;

        if (! empty($input['stockLocationId'])) {
            $location = StockLocation::whereKey($input['stockLocationId'])
                ->whereHas('balances', static fn ($balance) => $balance->where('stock_item_id', $item->id))
                ->first() ?? throw ValidationException::withMessages(['stockLocationId' => ['Cet article n’a pas de stock à cet emplacement.']]);
        }

        $customer = $this->context->customer();
        $user = $this->context->user();

        $adjustment = $this->create->execute(
            $item,
            $location,
            (string) $input['requestedQuantity'],
            (string) $input['reason'],
            $input['comment'] ?? null,
            new AuditContext($customer->organization_id, $user, $request->ip()),
        );

        $this->audit->record($customer, $user, 'stock_adjustment_request_created', $adjustment, null, [
            'stockAdjustmentRequestId' => $adjustment->id,
            'stockItemId' => $item->id,
        ], $request->ip());

        return $adjustment;
    }
}
