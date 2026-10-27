<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use Illuminate\Validation\ValidationException;

/**
 * L'agence qui reçoit une commande passée sans que personne ne la choisisse —
 * depuis le portail client, typiquement.
 *
 * Celle réglée sur la fiche du client d'abord, si elle est toujours active ;
 * sinon la première agence active de l'organisation, par code. Une
 * organisation sans agence active ne peut pas recevoir de commande : c'est
 * dit clairement plutôt que d'échouer sur une contrainte.
 */
final class CustomerDefaultAgency
{
    public function for(Customer $customer): string
    {
        $query = Agency::where('organization_id', $customer->organization_id)->where('status', 'active');

        $agencyId = ($customer->default_agency_id !== null
            ? (clone $query)->whereKey($customer->default_agency_id)->value('id')
            : null) ?? $query->orderBy('code')->value('id');

        if ($agencyId === null) {
            throw ValidationException::withMessages([
                'agency' => ['Votre transporteur n’a encore configuré aucune agence pour recevoir vos commandes.'],
            ]);
        }

        return (string) $agencyId;
    }
}
