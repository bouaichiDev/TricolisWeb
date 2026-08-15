<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;

/**
 * Périmètre des ressources d'intégration.
 *
 * Aucune des quatre classes de la Phase 8 ne porte `organizationId` : toutes
 * tiennent leur périmètre de leur client. Une ressource hors périmètre renvoie
 * **404**, jamais 403 — son existence ne se révèle pas.
 */
trait ResolvesCustomerScope
{
    /**
     * @param  Model&object{customer: Customer|null}  $model
     */
    protected function guardCustomerOwned(Model $model): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($model->customer?->organization_id === $organizationId, 404, 'Ressource introuvable.');

        return $organizationId;
    }

    protected function guardCustomer(Customer $customer): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($customer->organization_id === $organizationId, 404, 'Client introuvable.');

        return $organizationId;
    }
}
