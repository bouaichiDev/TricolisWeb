<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Communications\Models\CommunicationAttachment;
use App\Modules\Identity\Models\User;

/**
 * Ni `update` : les deux snapshots sont immuables, la route `PATCH` n'existe
 * pas.
 */
class CommunicationAttachmentPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'communication_attachments.view');
    }

    public function view(User $user, CommunicationAttachment $attachment): bool
    {
        return $this->hasPermission($user, $this->organizationOf($attachment), 'communication_attachments.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'communication_attachments.create');
    }

    public function delete(User $user, CommunicationAttachment $attachment): bool
    {
        return $this->hasPermission($user, $this->organizationOf($attachment), 'communication_attachments.delete');
    }

    private function organizationOf(CommunicationAttachment $attachment): ?string
    {
        return $attachment->communication?->organization_id;
    }
}
