<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Exceptions\CustomerOrderException;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerOrderCapabilities;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;

/**
 * Annulation depuis le portail : une **transition du workflow**, jamais une
 * suppression. `ChangeOrderStatus` — le service de l'interne — vérifie la
 * transition, exige le motif si le statut cible le demande, écrit l'audit et
 * publie `OrderStatusChanged`, qui déclenche les communications prévues.
 */
final readonly class CancelCustomerOrder
{
    public function __construct(
        private CurrentCustomerContext $context,
        private CustomerOrderCapabilities $capabilities,
        private ChangeOrderStatus $changeStatus,
        private CustomerPortalAudit $audit,
    ) {}

    public function execute(Order $order, ?string $reason, Request $request): Order
    {
        if (! $this->capabilities->for($order)['canCancel']) {
            throw CustomerOrderException::notCancellable();
        }

        $from = $order->status?->value;

        $order = $this->changeStatus->execute(
            $order,
            OrderStatus::CANCELLED,
            $this->context->user(),
            // Sans motif saisi, aucun code n'est inventé : si le workflow en
            // exige un, `ChangeOrderStatus` le réclame.
            $reason === null || $reason === '' ? null : 'customer_request',
            $reason,
            $request,
        );

        $this->audit->record($this->context->customer(), $this->context->user(), 'order_cancelled', $order, ['status' => $from], [
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'status' => $order->status->value,
            'reason' => $reason,
        ], $request->ip());

        return $order;
    }
}
