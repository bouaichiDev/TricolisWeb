<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Le compte est rattaché à plusieurs fournisseurs et n'a pas dit lequel ouvrir.
 *
 * Levée seulement **après** vérification du mot de passe : la liste des
 * fournisseurs n'est jamais rendue à qui ne détient pas le compte.
 */
final class ProviderSelectionRequired extends RuntimeException
{
    /**
     * @param  array<int, array{id: string, name: string}>  $providers
     */
    public function __construct(public readonly array $providers)
    {
        parent::__construct('Choisissez le fournisseur auquel vous connecter.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'provider_selection_required',
            'providers' => $this->providers,
        ], JsonResponse::HTTP_CONFLICT);
    }
}
