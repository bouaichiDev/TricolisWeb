<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Documente l'en-tête `X-Organization-Id` sur toutes les routes protégées par
 * le middleware `organization`. La vérification réelle est faite par
 * EnsureOrganizationContext.
 */
final class AddOrganizationHeader implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! in_array('organization', $routeInfo->route->gatherMiddleware(), true)) {
            return;
        }

        $operation->addParameters([
            Parameter::make('X-Organization-Id', 'header')
                ->required(true)
                ->description('ULID de l’organisation active. L’appartenance de l’utilisateur est vérifiée à chaque requête.')
                ->example('01JABCDEFGHJKMNPQRSTVWXYZ')
                ->setSchema(Schema::fromType(new StringType)),
        ]);
    }
}
