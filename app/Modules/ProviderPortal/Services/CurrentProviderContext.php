<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\Providers\Models\Provider;
use LogicException;

/**
 * Le fournisseur courant d'une requête du portail fournisseur.
 *
 * Rempli **une seule fois**, par le middleware `provider.portal`, après que la
 * chaîne complète a été vérifiée. Tout ce qui suit (contrôleurs, requêtes,
 * scopes) lit le fournisseur et l'organisation ici, et nulle part ailleurs —
 * jamais dans la requête HTTP.
 *
 * Enregistré en `scoped` : une instance par requête, jamais partagée.
 */
final class CurrentProviderContext
{
    private ?ProviderUser $membership = null;

    /** @var array<string, true> */
    private array $permissions = [];

    /**
     * @param  array<int, string>  $permissions
     */
    public function set(ProviderUser $membership, array $permissions): void
    {
        $this->membership = $membership;
        $this->permissions = array_fill_keys($permissions, true);
    }

    public function isResolved(): bool
    {
        return $this->membership !== null;
    }

    public function membership(): ProviderUser
    {
        return $this->membership ?? throw new LogicException('Aucun fournisseur courant : la route doit passer par `provider.portal`.');
    }

    public function provider(): Provider
    {
        return $this->membership()->provider;
    }

    public function providerId(): string
    {
        return $this->membership()->provider_id;
    }

    public function organizationId(): string
    {
        return $this->provider()->organization_id;
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
