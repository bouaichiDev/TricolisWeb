<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Un document d'une autre organisation est **introuvable**, pas interdit —
 * corrigé en Phase 10. Voir `BaseOrganizationPolicy::notFound()`.
 */
class DocumentPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'documents.view');
    }

    public function view(User $user, Document $document): Response|bool
    {
        return $this->scoped($user, $document, 'documents.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'documents.upload');
    }

    /**
     * Type de document qu'aucune permission ne permet d'effacer.
     *
     * Une preuve de livraison est deposee par le chauffeur et fait foi : la
     * supprimer detruirait la seule trace de ce qui a ete remis, et laisserait
     * une commande livree sans preuve. Une preuve erronee se conteste par une
     * reclamation, elle ne s'efface pas.
     */
    public const string PROTECTED_TYPE = 'pod';

    public function delete(User $user, Document $document): Response|bool
    {
        if (strtolower((string) $document->document_type) === self::PROTECTED_TYPE) {
            return Response::deny('Une preuve de livraison ne peut pas etre supprimee.');
        }

        return $this->scoped($user, $document, 'documents.delete');
    }

    private function scoped(User $user, Document $document, string $permission): Response|bool
    {
        if (! $this->seesOrganization($user, $document->organization_id)) {
            return $this->notFound();
        }

        return $this->hasPermission($user, $document->organization_id, $permission);
    }
}
