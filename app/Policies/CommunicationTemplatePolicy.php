<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Identity\Models\User;

class CommunicationTemplatePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'communication_templates.view');
    }

    public function view(User $user, CommunicationTemplate $template): bool
    {
        return $this->hasPermission($user, $template->organization_id, 'communication_templates.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'communication_templates.create');
    }

    public function update(User $user, CommunicationTemplate $template): bool
    {
        return $this->hasPermission($user, $template->organization_id, 'communication_templates.update');
    }

    public function delete(User $user, CommunicationTemplate $template): bool
    {
        return $this->hasPermission($user, $template->organization_id, 'communication_templates.delete');
    }
}
