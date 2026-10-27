<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use LogicException;

/**
 * Le Customer courant d'une requête du portail.
 *
 * Rempli **une seule fois**, par le middleware `customer.portal`, après que la
 * chaîne complète a été vérifiée : jeton portail → membre actif → client
 * actif. Tout ce qui vient ensuite (contrôleurs, requêtes, futurs modules
 * Orders, Stock, Claims) lit le client ici, et nulle part ailleurs.
 *
 * Enregistré en `scoped` : une instance par requête, jamais partagée.
 */
final class CurrentCustomerContext
{
    private ?CustomerUser $membership = null;

    /** @var array<string, true> */
    private array $permissions = [];

    /**
     * @param  array<int, string>  $permissions
     */
    public function set(CustomerUser $membership, array $permissions): void
    {
        $this->membership = $membership;
        $this->permissions = array_fill_keys($permissions, true);
    }

    public function isResolved(): bool
    {
        return $this->membership !== null;
    }

    public function membership(): CustomerUser
    {
        return $this->membership ?? throw new LogicException('Aucun client courant : la route doit passer par `customer.portal`.');
    }

    public function customer(): Customer
    {
        return $this->membership()->customer;
    }

    public function customerId(): string
    {
        return $this->membership()->customer_id;
    }

    public function organizationId(): string
    {
        return $this->customer()->organization_id;
    }

    public function user(): User
    {
        return $this->membership()->user;
    }

    public function can(string $permission): bool
    {
        return isset($this->permissions[$permission]);
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return array_keys($this->permissions);
    }
}
