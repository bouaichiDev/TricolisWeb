<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Models\PublicTrackingLink;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\Tracking\PublicTrackingLinks;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Révoque le lien public d'une commande (§24).
 *
 * `is_active = false`, `revoked_at = now()`, et la réponse publique mémorisée
 * est oubliée : le lien est inutilisable dès la requête suivante. Révoquer un
 * lien déjà inactif ne réécrit rien et n'ajoute rien à l'audit.
 */
final readonly class RevokePublicTrackingLink
{
    public function __construct(
        private CurrentCustomerContext $context,
        private PublicTrackingLinks $links,
        private CustomerPortalAudit $audit,
    ) {}

    public function execute(Order $order, Request $request): PublicTrackingLink
    {
        $link = PublicTrackingLink::where('order_id', $order->id)->first()
            ?? throw (new ModelNotFoundException)->setModel(PublicTrackingLink::class);

        if (! $link->is_active && $link->revoked_at !== null) {
            return $link;
        }

        $link->update(['is_active' => false, 'revoked_at' => now()]);
        $this->links->forget($link);

        $this->audit->record($this->context->customer(), $this->context->user(), 'public_tracking_link_revoked', $link, null, [
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
        ], $request->ip());

        return $link;
    }
}
