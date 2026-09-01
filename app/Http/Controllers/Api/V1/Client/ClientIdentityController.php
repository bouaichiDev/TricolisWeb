<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Services\CustomerApiContext;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * « Qui suis-je », vu par une clé cliente.
 *
 * Le premier appel que fait toute intégration : il confirme que la clé est
 * acceptée, dit pour quel client elle vaut, et énumère les droits qu'elle porte.
 * Sans lui, une intégration qui échoue plus loin ne saurait pas distinguer une
 * clé refusée d'un point d'entrée mal formé.
 *
 * Aucun droit n'est exigé pour l'atteindre : une clé sans permission doit
 * pouvoir constater qu'elle n'en a aucune, plutôt que de recevoir un 403 muet.
 */
final class ClientIdentityController extends Controller
{
    public function __invoke(CustomerApiContext $context): JsonResponse
    {
        $configuration = $context->configuration();
        $customer = $configuration?->customer;

        return ApiResponse::ok([
            'customer' => [
                'id' => $customer?->id,
                'name' => $customer?->name,
            ],
            'access' => [
                'name' => $configuration?->name,
                // Les droits sont rendus tels qu'ils sont stockés : c'est
                // exactement ce que le portail vérifiera à chaque appel.
                'permissions' => $configuration?->permissions ?? [],
            ],
        ]);
    }
}
