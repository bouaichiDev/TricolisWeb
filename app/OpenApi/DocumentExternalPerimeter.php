<?php

declare(strict_types=1);

namespace App\OpenApi;

use App\Http\Middleware\AuthenticateCustomerApiKey;
use App\Http\Requests\Api\V1\Customer\ServerControlledFields;
use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Documentation OpenAPI du **périmètre externe** — portail, lien public, API
 * par clé (Phase 8, §35).
 *
 * Scramble déduit les paramètres des règles de validation. Deux déductions
 * sont fausses pour un consommateur externe :
 *
 * - les champs `prohibited` (`customerId`, `organizationId`…) apparaissaient
 *   comme des filtres ; ils sont **refusés** (422) — le client vient du jeton ;
 * - `GET /client/orders` réutilise la requête de liste interne : ses filtres
 *   de tri, de dates ou d'agence apparaissaient, alors que le contrôleur ne lit
 *   que `search`, `status`, `perPage` et `page`.
 *
 * Les routes par clé reçoivent aussi l'en-tête `X-Api-Key` et leurs erreurs.
 */
final class DocumentExternalPerimeter implements OperationTransformer
{
    /** @var list<string> */
    private const array CLIENT_ORDER_FILTERS = ['search', 'status', 'perPage', 'page'];

    /** @var array<int, string> */
    private const array API_KEY_ERRORS = [
        401 => 'Clé absente, inconnue ou désactivée — sans dire laquelle.',
        403 => 'Clé sans le droit demandé, adresse hors de la liste autorisée, client ou organisation fermés.',
        404 => 'Introuvable pour le client de la clé.',
        429 => 'Débit dépassé pour cette clé ou cette adresse.',
    ];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $uri = $routeInfo->route->uri();

        if (! str_starts_with($uri, 'api/v1/customer/') && ! str_starts_with($uri, 'api/v1/client/') && ! str_starts_with($uri, 'api/v1/public/')) {
            return;
        }

        $operation->parameters = array_values(array_filter(
            $operation->parameters,
            static fn (mixed $parameter): bool => ! $parameter instanceof Parameter
                || $parameter->in !== 'query'
                || ! in_array($parameter->name, ServerControlledFields::TENANT, true),
        ));

        if ($uri === 'api/v1/client/orders') {
            $operation->parameters = array_values(array_filter(
                $operation->parameters,
                static fn (mixed $parameter): bool => ! $parameter instanceof Parameter
                    || $parameter->in !== 'query'
                    || in_array($parameter->name, self::CLIENT_ORDER_FILTERS, true),
            ));
        }

        $usesApiKey = array_filter(
            $routeInfo->route->gatherMiddleware(),
            static fn (mixed $middleware): bool => is_string($middleware) && ($middleware === 'customer-api' || str_starts_with($middleware, 'customer-api:')),
        );

        if ($usesApiKey !== []) {
            $this->documentApiKey($operation);
        }
    }

    private function documentApiKey(Operation $operation): void
    {
        $operation->addParameters([
            Parameter::make(AuthenticateCustomerApiKey::HEADER, 'header')
                ->required(true)
                ->description('Clé API du client, remise par le transporteur. Exemple fictif : ne jamais publier une vraie clé.')
                ->example('tk_exemple_non_valide')
                ->setSchema(Schema::fromType(new StringType)),
        ]);

        $error = (new ObjectType)
            ->addProperty('message', (new StringType)->setDescription('Message d’erreur lisible.'))
            ->setRequired(['message']);

        foreach (self::API_KEY_ERRORS as $status => $description) {
            $operation->addResponse(
                Response::make($status)
                    ->setDescription($description)
                    ->setContent('application/json', Schema::fromType($error)),
            );
        }
    }
}
