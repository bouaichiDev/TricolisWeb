<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Stock\Models\StockReservation;

/**
 * `release` est une permission distincte de `update` : libérer du stock rend de
 * la disponibilité à d'autres commandes, ce n'est pas une correction de saisie.
 *
 * Pas de `delete` : une réservation libérée reste, pour la traçabilité.
 */
class StockReservationPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'stock_reservations.view');
    }

    public function view(User $user, StockReservation $reservation): bool
    {
        return $this->hasPermission($user, $this->organizationOf($reservation), 'stock_reservations.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'stock_reservations.create');
    }

    public function update(User $user, StockReservation $reservation): bool
    {
        return $this->hasPermission($user, $this->organizationOf($reservation), 'stock_reservations.update');
    }

    public function release(User $user, StockReservation $reservation): bool
    {
        return $this->hasPermission($user, $this->organizationOf($reservation), 'stock_reservations.release');
    }

    private function organizationOf(StockReservation $reservation): ?string
    {
        return $reservation->stockItem?->customer?->organization_id;
    }
}
