<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\PriceList;

/**
 * Les droits sur un barème.
 *
 * **Un seul jeu de permissions pour tout le domaine tarifaire.** Règles,
 * conditions et matrices n'existent que dans une liste et n'ont aucun sens
 * hors d'elle : leur donner des droits séparés obligerait à en accorder trois
 * pour composer un seul barème, sans rien protéger de plus.
 */
class PriceListPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'price_lists.view');
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $this->hasPermission($user, $priceList->organization_id, 'price_lists.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'price_lists.create');
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $this->hasPermission($user, $priceList->organization_id, 'price_lists.update');
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $this->hasPermission($user, $priceList->organization_id, 'price_lists.delete');
    }
}
