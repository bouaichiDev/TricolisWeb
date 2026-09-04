<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Models\CustomerApiConfiguration;
use RuntimeException;

/**
 * L'accès client authentifié pour la requête en cours.
 *
 * Le pendant de `CurrentOrganizationContext`, pour l'autre façon d'entrer :
 * celle-là décrit un utilisateur de l'organisme, celle-ci un **client externe**
 * qui appelle avec sa clé. Les deux ne se rencontrent jamais — un jeton de
 * session n'ouvre pas les routes clientes, et une clé n'ouvre pas les routes
 * d'administration.
 *
 * Servi en singleton sur la durée de la requête : le middleware l'alimente une
 * fois, les contrôleurs le lisent. Passer la configuration de main en main à
 * travers les signatures aurait fait porter à chaque contrôleur une
 * responsabilité qui est celle du portail.
 */
final class CustomerApiContext
{
    private ?CustomerApiConfiguration $configuration = null;

    public function bind(CustomerApiConfiguration $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function configuration(): ?CustomerApiConfiguration
    {
        return $this->configuration;
    }

    /**
     * Le client au nom duquel la requête est faite.
     *
     * Lève plutôt que de rendre `null` : un contrôleur client atteint sans
     * contexte est un défaut de câblage des routes, pas un cas à gérer. Rendre
     * `null` inviterait à un `?? ''` qui ouvrirait la porte à tout le monde.
     */
    public function customerId(): string
    {
        $configuration = $this->configuration;

        if ($configuration === null) {
            throw new RuntimeException('Aucun accès client n’est authentifié pour cette requête.');
        }

        return $configuration->customer_id;
    }

    /** L'organisation du transporteur qui héberge ce client. */
    public function organizationId(): string
    {
        $configuration = $this->configuration;

        if ($configuration === null) {
            throw new RuntimeException('Aucun accès client n’est authentifié pour cette requête.');
        }

        $organizationId = $configuration->customer?->organization_id;

        if ($organizationId === null) {
            throw new RuntimeException('Cet accès client n’est rattaché à aucune organisation.');
        }

        return $organizationId;
    }

    /**
     * La clé porte-t-elle ce droit ?
     *
     * Une liste vide ne donne **rien**, contrairement aux adresses autorisées où
     * l'absence de restriction ouvre tout. La différence est voulue : une clé
     * sans droit ne peut rien faire, et l'écran de création le dit.
     */
    public function allows(string $permission): bool
    {
        $permissions = $this->configuration?->permissions;

        return is_array($permissions) && in_array($permission, $permissions, true);
    }
}
