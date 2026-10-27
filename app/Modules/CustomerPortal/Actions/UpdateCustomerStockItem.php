<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\Stock\Actions\UpdateStockItemAction;
use App\Modules\Stock\DTOs\UpdateStockItemData;
use App\Modules\Stock\Models\StockItem;
use App\Shared\Support\AuditContext;
use Illuminate\Http\Request;

/**
 * Modification des métadonnées d'un article par le client (§14).
 *
 * Seulement ce que l'interne permet de modifier sur un article — lien
 * catalogue, code, code-barres, description, statut — et par la même action
 * (`UpdateStockItemAction`, audit `stock_item.updated`). Le client, les
 * balances et l'emplacement ne sont pas des métadonnées : ils ne passent pas.
 */
final readonly class UpdateCustomerStockItem
{
    private const array METADATA = ['catalogItemId', 'articleCode', 'barcode', 'description', 'status'];

    public function __construct(
        private CurrentCustomerContext $context,
        private UpdateStockItemAction $update,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(StockItem $item, array $input, Request $request): StockItem
    {
        $customer = $this->context->customer();
        $user = $this->context->user();
        $changes = array_intersect_key($input, array_flip(self::METADATA));

        $updated = $this->update->execute(
            $item,
            UpdateStockItemData::fromValidated($changes),
            new AuditContext($customer->organization_id, $user, $request->ip()),
        );

        if ($changes !== []) {
            $this->audit->record($customer, $user, 'stock_item_updated', $updated, null, [
                'stockItemId' => $updated->id,
                'fields' => array_keys($changes),
            ], $request->ip());
        }

        return $updated;
    }
}
