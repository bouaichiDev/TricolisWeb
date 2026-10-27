<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Exceptions\CustomerOrderException;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerOrderCapabilities;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Support\CustomerOrderPayload;
use App\Modules\Orders\Actions\ReplaceOrderContent;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Modification d'une commande depuis le portail.
 *
 * ```
 * customer.orders.update → Customer ownership → canEdit → version → service commun → audit
 * ```
 *
 * La version est `updated_at`, tel que le portail l'a lu. Si la commande a
 * bougé depuis — un opérateur l'a planifiée, un collègue l'a modifiée —, la
 * réponse est un conflit, jamais un écrasement silencieux. `canEdit` est
 * **recalculé** au moment d'écrire, pas repris du chargement.
 */
final readonly class UpdateCustomerOrder
{
    private const array HEADER = [
        'orderDate' => 'order_date',
        'customerReference' => 'customer_reference',
        'externalReference' => 'external_reference',
        'workerRemark' => 'worker_remark',
    ];

    public function __construct(
        private CurrentCustomerContext $context,
        private CustomerOrderCapabilities $capabilities,
        private CustomerOrderPayload $payload,
        private ReplaceOrderContent $replace,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input  inclut `updatedAt`, la version lue
     */
    public function execute(Order $order, array $input, Request $request): Order
    {
        $updated = DB::transaction(function () use ($order, $input, $request): Order {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $this->capabilities->for($locked)['canEdit']) {
                throw CustomerOrderException::notEditable();
            }

            if (! $locked->updated_at?->equalTo(Carbon::parse($input['updatedAt']))) {
                throw CustomerOrderException::versionConflict();
            }

            $content = $this->payload->forUpdate($input, $locked);

            return $this->replace->execute(
                $locked,
                $this->header($content['header'], $locked),
                $content['lines'],
                $content['packages'],
                $content['services'],
                $this->context->user(),
                $request->ip(),
            );
        });

        $this->audit->record($this->context->customer(), $this->context->user(), 'order_updated', $updated, null, [
            'orderId' => $updated->id,
            'orderNumber' => $updated->order_number,
        ], $request->ip());

        return $updated;
    }

    /**
     * Les références absentes sont **vidées** : le formulaire envoie la
     * commande entière, une référence effacée doit disparaître.
     *
     * @param  array<string, mixed>  $header
     * @return array<string, mixed>
     */
    private function header(array $header, Order $order): array
    {
        $attributes = [];

        foreach (self::HEADER as $input => $column) {
            $attributes[$column] = $header[$input] ?? null;
        }

        // La date de commande ne se vide pas : absente du formulaire, elle
        // reste celle d'origine.
        $attributes['order_date'] ??= $order->order_date;

        return $attributes;
    }
}
