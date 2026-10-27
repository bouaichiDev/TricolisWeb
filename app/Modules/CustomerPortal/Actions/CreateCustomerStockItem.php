<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\Statuses\Services\DefaultStatuses;
use App\Modules\Stock\Actions\CreateStockItemAction;
use App\Modules\Stock\DTOs\CreateStockItemData;
use App\Modules\Stock\Models\StockItem;
use App\Shared\Database\MorphMap;
use App\Shared\Support\AuditContext;
use Illuminate\Http\Request;

/**
 * Ajout d'un article en stock depuis le portail (§13).
 *
 * Le service métier est celui de l'interne (`CreateStockItemAction`) : même
 * garde (`StockScopeGuard` — l'article du catalogue doit appartenir au
 * client), même transaction, même audit `stock_item.created`. Le portail
 * impose le client, et le statut par défaut du référentiel quand aucun n'est
 * choisi.
 *
 * **Aucune balance ni aucun mouvement n'est créé** : un article n'est pas du
 * stock physique. Sans balance, ses quantités valent 0.
 */
final readonly class CreateCustomerStockItem
{
    public function __construct(
        private CurrentCustomerContext $context,
        private CreateStockItemAction $create,
        private DefaultStatuses $defaults,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(array $input, Request $request): StockItem
    {
        $customer = $this->context->customer();
        $user = $this->context->user();
        $status = $input['status']
            ?? $this->defaults->chosen(MorphMap::STOCK_ITEM)
            ?? DefaultStatuses::columnDefault('stock_items');

        $item = $this->create->execute(new CreateStockItemData(
            customerId: $customer->id,
            articleCode: $input['articleCode'],
            status: (string) $status,
            catalogItemId: $input['catalogItemId'] ?? null,
            barcode: $input['barcode'] ?? null,
            description: $input['description'] ?? null,
        ), new AuditContext($customer->organization_id, $user, $request->ip()));

        $this->audit->record($customer, $user, 'stock_item_created', $item, null, [
            'stockItemId' => $item->id,
            'articleCode' => $item->article_code,
        ], $request->ip());

        return $item;
    }
}
