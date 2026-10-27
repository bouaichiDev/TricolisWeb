<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Concerns;

use App\Modules\Claims\Models\Claim;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Retrouve une réclamation **du Customer courant**, et nulle part ailleurs.
 *
 * Même principe que les commandes : pas de liaison implicite de route, la
 * requête porte d'emblée `organization_id` et `customer_id`. La réclamation
 * d'un autre client répond exactement comme une réclamation inexistante — 404.
 */
trait ResolvesCustomerClaim
{
    protected function claim(CurrentCustomerContext $context, string $id): Claim
    {
        if (! Str::isUlid($id)) {
            throw (new ModelNotFoundException)->setModel(Claim::class);
        }

        return Claim::query()
            ->where('organization_id', $context->organizationId())
            ->where('customer_id', $context->customerId())
            ->findOrFail($id);
    }
}
