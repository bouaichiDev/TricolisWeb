<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PublicTracking;

use App\Http\Controllers\Controller;
use App\Modules\CustomerPortal\Services\Tracking\OrderTrackingBuilder;
use App\Modules\CustomerPortal\Services\Tracking\PublicTrackingLinks;
use App\Modules\CustomerPortal\Services\Tracking\PublicTrackingPresenter;
use App\Modules\Organizations\Services\OrganizationLogo;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suivi public d'une commande, sans authentification (§26).
 *
 * Jeton → empreinte → lien → actif et non expiré → commande → présentateur
 * public → réponse minimale. Tout échec répond la même chose — 404
 * « Tracking indisponible » — pour ne rien apprendre à qui énumère.
 *
 * La réponse est mémorisée quelques secondes par lien ; la validité du lien,
 * elle, est vérifiée à **chaque** requête, et la révocation oublie la réponse
 * mémorisée : un lien révoqué ne sert plus rien, pas même depuis le cache.
 */
class PublicTrackingController extends Controller
{
    public function __construct(private readonly PublicTrackingLinks $links) {}

    public function show(string $token, OrderTrackingBuilder $builder, PublicTrackingPresenter $presenter): JsonResponse
    {
        $link = $this->links->resolve($token);

        if ($link === null) {
            return $this->unavailable();
        }

        $this->links->touch($link);
        $order = $link->order;

        $payload = Cache::remember(
            $this->links->cacheKey($link),
            Config::integer('customer_portal.public_tracking.cache_seconds', 15),
            static fn (): array => $presenter->present($builder->build($order), $order->customer->organization),
        );

        return $this->private(ApiResponse::ok($payload));
    }

    /**
     * Le logo de l'organisation du transporteur, pour la page publique.
     *
     * Servi derrière le même jeton que le suivi : le disque `local` n'expose
     * aucun chemin, et un lien révoqué ne sert plus non plus le logo.
     */
    public function logo(string $token, OrganizationLogo $logo): Response
    {
        $organization = $this->links->resolve($token)?->order?->customer?->organization;

        if ($organization === null || ! $logo->exists($organization)) {
            return $this->unavailable();
        }

        return $this->private(Storage::disk('local')->response(
            (string) $organization->logo_path,
            'logo',
            ['Content-Type' => $organization->logo_mime_type ?? 'image/png'],
        ));
    }

    private function unavailable(): JsonResponse
    {
        return $this->private(response()->json(['message' => 'Tracking indisponible', 'code' => 'TRACKING_UNAVAILABLE'], 404));
    }

    /**
     * Ni cache partagé, ni indexation, ni référent : l'URL porte le secret.
     *
     * @template T of Response
     *
     * @param  T  $response
     * @return T
     */
    private function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
