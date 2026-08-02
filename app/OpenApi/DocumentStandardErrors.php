<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Ajoute les réponses d'erreur communes à toutes les opérations authentifiées,
 * afin que chaque endpoint documente ses erreurs possibles (§28).
 */
final class DocumentStandardErrors implements OperationTransformer
{
    /** @var array<int, string> */
    private const array ERRORS = [
        401 => 'Non authentifié : jeton Sanctum absent, expiré ou révoqué.',
        403 => 'Interdit : permission manquante ou organisation active non autorisée.',
        404 => 'Introuvable dans le périmètre autorisé.',
        422 => 'Données invalides ou en-tête d’organisation malformé.',
    ];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $this->isAuthenticated($routeInfo)) {
            return;
        }

        $documented = array_map(
            static fn (Response $response): ?int => $response->code,
            array_filter($operation->responses ?? [], static fn ($response): bool => $response instanceof Response),
        );

        foreach (self::ERRORS as $status => $description) {
            if (in_array($status, $documented, true)) {
                continue;
            }

            $operation->addResponse(
                Response::make($status)
                    ->setDescription($description)
                    ->setContent('application/json', Schema::fromType($this->errorSchema()))
            );
        }
    }

    private function isAuthenticated(RouteInfo $routeInfo): bool
    {
        return in_array('auth:sanctum', $routeInfo->route->gatherMiddleware(), true);
    }

    private function errorSchema(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('message', (new StringType)->setDescription('Message d’erreur lisible.'))
            ->setRequired(['message']);
    }
}
