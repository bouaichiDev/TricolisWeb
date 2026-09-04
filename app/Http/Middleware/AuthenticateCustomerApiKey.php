<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Services\ApiKeyGenerator;
use App\Modules\Integrations\Services\CustomerApiContext;
use App\Modules\Integrations\Services\IpAllowList;
use App\Shared\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le portail des clients : authentifie une requête par sa clé API.
 *
 * Jusqu'ici, `customer_api_configurations` était un registre sans lecteur — les
 * clés s'émettaient et se renouvelaient, mais aucun point d'entrée ne les
 * acceptait, `allowedIps` et `permissions` n'étaient jamais appliqués, et
 * `lastUsedAt` restait vide. Ce middleware est ce lecteur.
 *
 * Il ne s'applique **qu'aux routes clientes**. Les routes d'administration
 * restent derrière `auth:sanctum` : une clé client ne doit pas pouvoir emprunter
 * le chemin d'un utilisateur du transporteur, et réciproquement.
 *
 * Le droit exigé se déclare sur la route — `customer-api:orders.view` — parce
 * qu'il dépend de ce que la route fait, et de rien d'autre. Sans paramètre, la
 * clé est seulement authentifiée : c'est ce qu'il faut pour un point d'entrée
 * qui ne dit que « qui suis-je ».
 *
 * L'ordre des contrôles n'est pas indifférent. La clé d'abord, parce qu'une
 * réponse différente selon qu'un accès existe ou non renseignerait un attaquant
 * sur les clés valides. L'adresse ensuite : elle protège même une clé volée. Le
 * droit en dernier, puisqu'il suppose l'accès déjà reconnu.
 */
final readonly class AuthenticateCustomerApiKey
{
    /** L'en-tête que le client présente. */
    public const string HEADER = 'X-Api-Key';

    public function __construct(
        private ApiKeyGenerator $keys,
        private IpAllowList $addresses,
        private CustomerApiContext $context,
    ) {}

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $presented = $request->header(self::HEADER);

        if (! is_string($presented) || $presented === '') {
            return $this->refuse('La clé API est requise.', JsonResponse::HTTP_UNAUTHORIZED);
        }

        // L'empreinte est déterministe : la recherche se fait dessus, jamais sur
        // la clé — qui n'est stockée nulle part.
        $configuration = CustomerApiConfiguration::query()
            ->where('api_key_hash', $this->keys->hash($presented))
            ->with('customer:id,organization_id,name,status')
            ->first();

        // Un accès inconnu et un accès désactivé donnent la même réponse :
        // distinguer les deux dirait à un appelant qu'il a trouvé une clé
        // valide, seulement fermée.
        if ($configuration === null || ! $configuration->is_active) {
            return $this->refuse('Clé API inconnue ou révoquée.', JsonResponse::HTTP_UNAUTHORIZED);
        }

        if ($configuration->customer === null) {
            return $this->refuse('Cet accès n’est plus rattaché à un client.', JsonResponse::HTTP_FORBIDDEN);
        }

        /** @var list<string>|null $allowed */
        $allowed = $configuration->allowed_ips;

        if (! $this->addresses->permits($allowed, (string) $request->ip())) {
            return $this->refuse(
                'Cette adresse n’est pas autorisée pour cette clé.',
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        $this->context->bind($configuration);

        if ($permission !== null && ! $this->context->allows($permission)) {
            return $this->refuse(
                'Cette clé ne porte pas le droit nécessaire.',
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        // Sans événement : ce n'est pas une modification métier, et la faire
        // passer par `update()` inscrirait un `updated_at` qui ne veut rien dire
        // ici. Écrit après les contrôles, pour ne dater que les appels admis.
        $configuration->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }

    private function refuse(string $message, int $status): JsonResponse
    {
        return ApiResponse::error($message, $status);
    }
}
