<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\User;

class DocumentPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->hasPermission($user, $document->organization_id, 'documents.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'documents.upload');
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->hasPermission($user, $document->organization_id, 'documents.delete');
    }
}
