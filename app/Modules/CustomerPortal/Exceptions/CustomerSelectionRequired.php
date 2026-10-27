<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Le compte est membre de plusieurs clients et n'a pas dit lequel ouvrir.
 *
 * Levée seulement **après** vérification du mot de passe : la liste des
 * clients n'est jamais rendue à qui ne détient pas le compte.
 */
final class CustomerSelectionRequired extends RuntimeException
{
    /**
     * @param  array<int, array{id: string, name: string}>  $customers
     */
    public function __construct(public readonly array $customers)
    {
        parent::__construct('Choisissez le client auquel vous connecter.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'customer_selection_required',
            'customers' => $this->customers,
        ], JsonResponse::HTTP_CONFLICT);
    }
}
