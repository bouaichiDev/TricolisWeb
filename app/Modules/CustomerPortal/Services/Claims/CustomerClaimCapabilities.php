<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Claims;

use App\Modules\Claims\Models\Claim;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;

/**
 * Ce qu'un membre du portail peut faire d'une de ses réclamations.
 *
 * Une seule règle d'état existe dans le module : **ouverte** tant que
 * `closed_at` est nul (`Claim::isClosed`). Aucun statut n'est interprété —
 * le référentiel n'en décrit pas le sens pour les réclamations.
 *
 * - modifier titre, description, type : `customer.claims.update`, ouverte ;
 * - joindre une pièce : `customer.claims.create`, ouverte ;
 * - supprimer : jamais depuis le portail (voir le rapport de phase).
 */
final readonly class CustomerClaimCapabilities
{
    public function __construct(private CurrentCustomerContext $context) {}

    /**
     * @return array{canEdit: bool, canAttach: bool}
     */
    public function for(Claim $claim): array
    {
        $open = ! $claim->isClosed();

        return [
            'canEdit' => $open && $this->context->can('customer.claims.update'),
            'canAttach' => $open && $this->context->can('customer.claims.create'),
        ];
    }
}
