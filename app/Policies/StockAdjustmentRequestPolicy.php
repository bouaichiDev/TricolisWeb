<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Stock\Models\StockAdjustmentRequest;

/**
 * Demandes de correction déposées au portail, tranchées en interne.
 *
 * `review` couvre l'acceptation **et** le refus : les deux rendent la même
 * décision, dans un sens ou dans l'autre. Ni `create`, ni `update`, ni
 * `delete` : une demande naît au portail et ne se rejoue pas.
 */
class StockAdjustmentRequestPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'stock_adjustment_requests.view');
    }

    public function view(User $user, StockAdjustmentRequest $request): bool
    {
        return $this->hasPermission($user, $request->organization_id, 'stock_adjustment_requests.view');
    }

    public function review(User $user, StockAdjustmentRequest $request): bool
    {
        return $this->hasPermission($user, $request->organization_id, 'stock_adjustment_requests.review');
    }
}
