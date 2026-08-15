<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Tracking\Models\TrackingEvent;

/**
 * L'événement de suivi porte son organisation : la permission s'évalue dessus.
 *
 * Ni `update`, ni `delete` : les routes n'existent pas. Un événement est une
 * donnée historique, une nouvelle occurrence produit une nouvelle ligne.
 */
class TrackingEventPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tracking_events.view');
    }

    public function view(User $user, TrackingEvent $event): bool
    {
        return $this->hasPermission($user, $event->organization_id, 'tracking_events.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tracking_events.create');
    }
}
