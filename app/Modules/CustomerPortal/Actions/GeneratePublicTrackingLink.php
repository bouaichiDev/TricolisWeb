<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Models\PublicTrackingLink;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\Tracking\PublicTrackingLinks;
use App\Modules\CustomerPortal\Services\Tracking\PublicTrackingSettings;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée — ou régénère — le lien public de suivi d'une commande (§22).
 *
 * Le jeton vient de `Str::random`, tiré de `random_bytes` : une source
 * cryptographique, 64 caractères, rien de dérivé de la commande. Seule son
 * empreinte est écrite ; le jeton brut n'est rendu qu'ici, une seule fois.
 *
 * Régénérer remplace l'empreinte sur la même ligne : l'ancien lien cesse de
 * fonctionner à l'instant, sans qu'il faille le révoquer à part.
 *
 * L'expiration vient de la demande, sinon du réglage de l'organisation, sinon
 * n'existe pas — aucune durée n'est décidée ici.
 */
final readonly class GeneratePublicTrackingLink
{
    public function __construct(
        private CurrentCustomerContext $context,
        private PublicTrackingSettings $settings,
        private PublicTrackingLinks $links,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @return array{link: PublicTrackingLink, token: string}
     */
    public function execute(Order $order, ?string $expiresAt, Request $request): array
    {
        $customer = $this->context->customer()->loadMissing('organization');
        $user = $this->context->user();
        $token = Str::random(PublicTrackingLink::TOKEN_LENGTH);
        $lifetime = $this->settings->linkLifetimeDays($customer->organization);

        $expiry = match (true) {
            $expiresAt !== null => Carbon::parse($expiresAt)->endOfDay(),
            $lifetime !== null => now()->addDays($lifetime),
            default => null,
        };

        $link = DB::transaction(static fn (): PublicTrackingLink => PublicTrackingLink::updateOrCreate(
            ['order_id' => $order->id],
            [
                'token_hash' => PublicTrackingLink::hashToken($token),
                'is_active' => true,
                'expires_at' => $expiry,
                'created_by' => $user->id,
                'revoked_at' => null,
                'last_access_at' => null,
                'access_count' => 0,
            ],
        ));

        $this->links->forget($link);

        // Ni le jeton ni son empreinte dans l'audit : seulement ce qui a changé.
        $this->audit->record($customer, $user, 'public_tracking_link_created', $link, null, [
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'expiresAt' => $expiry?->toIso8601String(),
            'regenerated' => ! $link->wasRecentlyCreated,
        ], $request->ip());

        return ['link' => $link, 'token' => $token];
    }
}
