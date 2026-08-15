<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;

/**
 * La preuve de livraison n'a pas d'organisation propre : sa permission est
 * évaluée dans l'organisation de sa commande.
 *
 * Ni `update`, ni `delete` : une preuve est historique, et le §29 interdit sa
 * suppression physique par l'API.
 */
class ProofOfDeliveryPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'proofs_of_delivery.view');
    }

    public function view(User $user, ProofOfDelivery $proof): bool
    {
        return $this->hasPermission($user, $this->organizationOf($proof), 'proofs_of_delivery.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'proofs_of_delivery.create');
    }

    private function organizationOf(ProofOfDelivery $proof): ?string
    {
        return $proof->order?->organization_id;
    }
}
