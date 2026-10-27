<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Support\CustomerOrderPayload;
use App\Modules\Orders\Actions\CreateFullOrder;
use App\Modules\Orders\DTOs\CreateOrderData;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Création d'une commande depuis le portail.
 *
 * Le service métier est **celui de l'interne** (`CreateFullOrder`) : même
 * numérotation, mêmes contrôles, même transaction, même événement
 * `OrderCreated` — donc mêmes communications. Le portail ne fait que
 * traduire la saisie et imposer le client (`CustomerOrderPayload`).
 *
 * La traduction elle-même tourne dans la transaction : un destinataire
 * ponctuel créé pour un service disparaît si la commande échoue.
 */
final readonly class CreateCustomerOrder
{
    public function __construct(
        private CurrentCustomerContext $context,
        private CustomerOrderPayload $payload,
        private CreateFullOrder $create,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(array $input, Request $request): Order
    {
        $customer = $this->context->customer()->loadMissing('organization');
        $user = $this->context->user();

        $order = DB::transaction(fn (): Order => $this->create->execute(
            CreateOrderData::fromArray($this->payload->forCreation($input, $customer)),
            $customer->organization_id,
            $user,
            $request,
        ));

        $this->audit->record($customer, $user, 'order_created', $order, null, [
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
        ], $request->ip());

        return $order;
    }
}
