<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\Orders\Actions\DuplicateOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Duplication depuis le portail, par le service interne `DuplicateOrder`.
 *
 * Il copie l'en-tête utile, les lignes, les colis et leurs affectations, les
 * services avec contacts et colis ; il ne copie **rien d'exécution** : ni
 * tournée, ni suivi, ni preuve, ni facture, ni communication, ni réclamation,
 * ni document. La copie repart au statut initial du workflow
 * (`DefaultStatuses`), jamais à un `draft` écrit en dur.
 *
 * La copie porte la source `customer_portal` : c'est le client qui l'a créée.
 */
final readonly class DuplicateCustomerOrder
{
    public function __construct(
        private CurrentCustomerContext $context,
        private DuplicateOrder $duplicate,
        private CustomerPortalAudit $audit,
    ) {}

    public function execute(Order $source, Request $request): Order
    {
        $copy = DB::transaction(function () use ($source, $request): Order {
            $copy = $this->duplicate->execute($source, ['documents' => false], $this->context->user(), $request);
            $copy->forceFill(['source' => OrderSource::CUSTOMER_PORTAL->value])->save();

            return $copy;
        });

        $this->audit->record($this->context->customer(), $this->context->user(), 'order_duplicated', $copy, null, [
            'orderId' => $copy->id,
            'orderNumber' => $copy->order_number,
            'sourceOrderId' => $source->id,
        ], $request->ip());

        return $copy;
    }
}
