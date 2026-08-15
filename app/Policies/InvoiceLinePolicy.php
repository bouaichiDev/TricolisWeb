<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Identity\Models\User;

/**
 * La ligne tient son périmètre de sa facture : c'est elle qui porte
 * l'organisation.
 */
class InvoiceLinePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'invoice_lines.view');
    }

    public function view(User $user, InvoiceLine $line): bool
    {
        return $this->hasPermission($user, $this->organizationOf($line), 'invoice_lines.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'invoice_lines.create');
    }

    public function update(User $user, InvoiceLine $line): bool
    {
        return $this->hasPermission($user, $this->organizationOf($line), 'invoice_lines.update');
    }

    public function delete(User $user, InvoiceLine $line): bool
    {
        return $this->hasPermission($user, $this->organizationOf($line), 'invoice_lines.delete');
    }

    private function organizationOf(InvoiceLine $line): ?string
    {
        return $line->invoice?->organization_id;
    }
}
