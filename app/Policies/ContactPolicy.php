<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;

class ContactPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        $organizationId = $this->organizationIdForContact($contact);

        return $this->contactBelongsToOrganization($contact, $user) && $this->hasPermission($user, $organizationId, 'contacts.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->hasPermission($user, $this->organizationIdForContact($contact), 'contacts.update');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->hasPermission($user, $this->organizationIdForContact($contact), 'contacts.delete');
    }

    private function contactBelongsToOrganization(Contact $contact, User $user): bool
    {
        $organizationIds = OrganizationUser::where('user_id', $user->id)
            ->pluck('organization_id');

        return $contact->entityContacts()
            ->whereIn('organization_id', $organizationIds)
            ->exists();
    }

    private function organizationIdForContact(Contact $contact): ?string
    {
        return $contact->entityContacts()
            ->value('organization_id');
    }
}
