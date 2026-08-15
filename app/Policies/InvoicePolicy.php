<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Identity\Models\User;

/**
 * La facture porte son organisation : la permission s'évalue dessus.
 */
class InvoicePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, $invoice->organization_id, 'invoices.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, $invoice->organization_id, 'invoices.update');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, $invoice->organization_id, 'invoices.delete');
    }
}
