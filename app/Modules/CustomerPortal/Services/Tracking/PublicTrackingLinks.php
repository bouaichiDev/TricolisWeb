<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\CustomerPortal\Models\PublicTrackingLink;
use App\Modules\Customers\Enums\CustomerStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Tout ce qui touche au lien public hors création et révocation : son URL, son
 * état tel que le portail l'affiche, la résolution d'un jeton reçu et la trace
 * légère des consultations.
 *
 * **Résoudre un jeton ne dit jamais pourquoi il échoue.** Inconnu, altéré,
 * expiré, révoqué, commande disparue, client désactivé : tout rend `null`, et
 * l'API publique répond la même chose. Distinguer « expiré » d'« inconnu »
 * apprendrait à qui énumère qu'il est tombé sur un vrai lien.
 */
final readonly class PublicTrackingLinks
{
    /** Consultations comptées au plus une fois par minute et par lien. */
    private const int ACCESS_WINDOW_SECONDS = 60;

    public function url(string $token): string
    {
        return rtrim(Config::string('customer_portal.public_tracking.url'), '/').'/t/'.$token;
    }

    public function resolve(string $token): ?PublicTrackingLink
    {
        // Un jeton mal formé ne coûte pas même une requête.
        if (preg_match('/^[A-Za-z0-9]{'.PublicTrackingLink::TOKEN_LENGTH.'}$/', $token) !== 1) {
            return null;
        }

        $link = PublicTrackingLink::with('order.customer.organization')
            ->where('token_hash', PublicTrackingLink::hashToken($token))
            ->first();

        $order = $link?->order;
        $customer = $order?->customer;

        if ($link === null || ! $link->isUsable() || $order === null || $customer === null || $customer->organization === null) {
            return null;
        }

        // Le transporteur qui coupe le suivi d'un client coupe aussi ses liens.
        if ($customer->status !== CustomerStatus::ACTIVE || ! $customer->tracking_enabled) {
            return null;
        }

        return $link;
    }

    /** `last_access_at` plutôt que l'audit : un rafraîchissement n'est pas un événement. */
    public function touch(PublicTrackingLink $link): void
    {
        if ($link->last_access_at !== null && $link->last_access_at->diffInSeconds(now()) < self::ACCESS_WINDOW_SECONDS) {
            return;
        }

        PublicTrackingLink::whereKey($link->id)->update([
            'last_access_at' => now(),
            'access_count' => $link->access_count + 1,
        ]);
    }

    public function cacheKey(PublicTrackingLink $link): string
    {
        return 'public-tracking:'.$link->id;
    }

    /** Oublie la réponse mémorisée : un lien révoqué ou régénéré ne sert plus rien de périmé. */
    public function forget(PublicTrackingLink $link): void
    {
        Cache::forget($this->cacheKey($link));
    }

    /**
     * L'état du lien d'une commande, tel que le portail l'affiche (§23).
     *
     * `publicUrl` n'existe qu'à la création : le jeton brut n'est pas gardé,
     * il n'y a donc rien à reconstituer ensuite. L'écran propose de régénérer.
     *
     * @return array<string, mixed>
     */
    public function present(?PublicTrackingLink $link, ?string $token = null): array
    {
        if ($link === null) {
            return ['exists' => false, 'state' => 'none', 'active' => false, 'publicUrl' => null];
        }

        return [
            'exists' => true,
            'state' => $link->state(),
            'active' => $link->isUsable(),
            'expiresAt' => $link->expires_at?->toIso8601String(),
            'createdAt' => $link->created_at?->toIso8601String(),
            'updatedAt' => $link->updated_at?->toIso8601String(),
            'revokedAt' => $link->revoked_at?->toIso8601String(),
            'lastAccessAt' => $link->last_access_at?->toIso8601String(),
            'accessCount' => $link->access_count,
            'publicUrl' => $token === null ? null : $this->url($token),
        ];
    }
}
