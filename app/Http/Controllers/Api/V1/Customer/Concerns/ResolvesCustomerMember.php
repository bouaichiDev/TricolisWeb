<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Concerns;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Retrouve un membre **dans le Customer courant**, et nulle part ailleurs.
 *
 * C'est le point où l'isolation se joue pour les routes `users/{id}` :
 * l'identifiant d'un membre d'un autre client répond exactement comme un
 * identifiant qui n'existe pas — `404` — pour ne rien confirmer.
 */
trait ResolvesCustomerMember
{
    protected function member(CurrentCustomerContext $context, string $id): CustomerUser
    {
        if (! Str::isUlid($id)) {
            throw (new ModelNotFoundException)->setModel(CustomerUser::class);
        }

        return CustomerUser::forCustomer($context->customerId())
            ->with(['user', 'roles', 'customer'])
            ->findOrFail($id);
    }
}
