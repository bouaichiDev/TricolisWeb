<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Documents;

use App\Modules\Claims\Models\Claim;
use App\Modules\CustomerPortal\Queries\CustomerProofsOfDelivery;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les documents qu'un membre du portail peut ouvrir (§14).
 *
 * Connaître un identifiant ne suffit jamais : un document n'est accessible
 * que par un **chemin métier** jusqu'au Customer courant, et chaque chemin
 * exige sa permission :
 *
 * - `DocumentLink → Order` ou `→ OrderService → Order` : `customer.orders.documents.view` ;
 * - `DocumentLink → Claim` du client : `customer.claims.view` ;
 * - signature ou photo d'une preuve de livraison cohérente du client :
 *   `customer.orders.proofs_of_delivery.view`.
 *
 * Sans aucun chemin ouvert, la requête ne rend rien : le document répond 404,
 * comme s'il n'existait pas.
 */
final readonly class CustomerDocumentAccess
{
    public function __construct(private CurrentCustomerContext $context) {}

    /**
     * @return Builder<Document>
     */
    public function query(): Builder
    {
        $customer = $this->context->customer();
        $orders = CustomerProofsOfDelivery::orderIds($customer);

        return Document::query()
            ->where('organization_id', $customer->organization_id)
            ->where(function (Builder $paths) use ($customer, $orders): void {
                $paths->whereRaw('1 = 0');

                if ($this->context->can('customer.orders.documents.view')) {
                    $services = OrderService::query()->select('id')->whereIn('order_id', $orders);
                    $paths->orWhereHas('links', static fn (Builder $link) => $link->where('entity_type', MorphMap::ORDER)->whereIn('entity_id', $orders))
                        ->orWhereHas('links', static fn (Builder $link) => $link->where('entity_type', MorphMap::ORDER_SERVICE)->whereIn('entity_id', $services));
                }

                if ($this->context->can('customer.claims.view')) {
                    $claims = Claim::query()->select('id')
                        ->where('organization_id', $customer->organization_id)
                        ->where('customer_id', $customer->id);
                    $paths->orWhereHas('links', static fn (Builder $link) => $link->where('entity_type', MorphMap::CLAIM)->whereIn('entity_id', $claims));
                }

                if ($this->context->can('customer.orders.proofs_of_delivery.view')) {
                    $paths->orWhereIn('id', CustomerProofsOfDelivery::query($customer)->select('signature_document_id')->whereNotNull('signature_document_id'))
                        ->orWhereIn('id', CustomerProofsOfDelivery::query($customer)->select('photo_document_id')->whereNotNull('photo_document_id'));
                }
            });
    }

    /**
     * Les documents rattachés à une entité **et** accessibles par un chemin.
     *
     * @return Builder<Document>
     */
    public function linkedTo(string $entityType, string $entityId): Builder
    {
        return $this->query()->whereHas('links', static fn (Builder $link) => $link->where('entity_type', $entityType)->where('entity_id', $entityId));
    }
}
