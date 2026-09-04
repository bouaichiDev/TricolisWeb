<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Templates\Models\Template;

/**
 * Une seule famille de permissions pour une seule table.
 *
 * Le §0.31 remplace `communication_templates.*` par `templates.*` : un modèle de
 * facture et un modèle d'e-mail vivent au même endroit, et exiger deux
 * permissions pour un seul écran aurait laissé la moitié de la liste
 * inaccessible sans raison lisible.
 */
class TemplatePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'templates.view');
    }

    public function view(User $user, Template $template): bool
    {
        return $this->hasPermission($user, $template->organization_id, 'templates.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'templates.create');
    }

    public function update(User $user, Template $template): bool
    {
        return $this->hasPermission($user, $template->organization_id, 'templates.update');
    }

    public function delete(User $user, Template $template): bool
    {
        return $this->hasPermission($user, $template->organization_id, 'templates.delete');
    }
}
