<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Auth\Access\Response;

/**
 * Un contact d'une autre organisation est **introuvable**, pas interdit —
 * corrigé en Phase 10. Voir `BaseOrganizationPolicy::notFound()`.
 *
 * Comme l'adresse, le contact tient son périmètre de ses `EntityContact` : un
 * contact sans rattachement est hors périmètre.
 */
class ContactPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'contacts.view');
    }

    public function view(User $user, Contact $contact): Response|bool
    {
        return $this->scoped($user, $contact, 'contacts.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'contacts.create');
    }

    public function update(User $user, Contact $contact): Response|bool
    {
        return $this->scoped($user, $contact, 'contacts.update');
    }

    public function delete(User $user, Contact $contact): Response|bool
    {
        return $this->scoped($user, $contact, 'contacts.delete');
    }

    private function scoped(User $user, Contact $contact, string $permission): Response|bool
    {
        if (! $this->contactBelongsToOrganization($contact, $user)) {
            return $this->notFound();
        }

        return $this->hasPermission($user, $this->organizationIdForContact($contact), $permission);
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
