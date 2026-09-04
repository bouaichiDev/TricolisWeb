<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Tracking\Models\TrackingEventDefinition;

/**
 * Ce que porte l'organisme, l'organisme le gouverne.
 *
 * Rien de plateforme ici : ces réglages décrivent la façon dont **un** organisme
 * travaille, pas le domaine commun.
 */
class TrackingEventDefinitionPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tracking_event_definitions.view');
    }

    public function view(User $user, TrackingEventDefinition $model): bool
    {
        return $this->hasPermission($user, $model->organization_id, 'tracking_event_definitions.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tracking_event_definitions.create');
    }

    public function update(User $user, TrackingEventDefinition $model): bool
    {
        return $this->hasPermission($user, $model->organization_id, 'tracking_event_definitions.update');
    }

    public function delete(User $user, TrackingEventDefinition $model): bool
    {
        return $this->hasPermission($user, $model->organization_id, 'tracking_event_definitions.delete');
    }
}
