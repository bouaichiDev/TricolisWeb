<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Support;

use App\Modules\Customers\Models\Customer;
use App\Modules\Providers\Models\Provider;
use App\Shared\Enums\OrganizationStatus;

/**
 * L'organisation du client autorise-t-elle encore l'accès externe ?
 *
 * Aucune règle globale n'existait sur `Organization.status` (Phase 7, §11) :
 * celle-ci ne vaut que pour le **périmètre externe** — portail client et API
 * par clé —, l'interne n'est pas touché. Un transporteur suspendu ou fermé ne
 * sert plus ses clients ; `pending` (organisation en cours d'ouverture) et
 * `active` restent ouverts. Aucun statut n'est inventé.
 *
 * Décision technique à valider par le métier : docs/customer-portal/security.md.
 */
final class PortalOrganizationGate
{
    /** @var list<OrganizationStatus> */
    private const array CLOSED = [OrganizationStatus::SUSPENDED, OrganizationStatus::CLOSED];

    public static function allows(Customer $customer): bool
    {
        // Chargement complet : la relation reste en cache pour la suite de la
        // requête, qui lit aussi les réglages de l'organisation.
        $customer->loadMissing('organization');
        $status = $customer->organization?->status;

        return $status !== null && ! in_array($status, self::CLOSED, true);
    }

    /**
     * Même règle pour le portail fournisseur : un transporteur suspendu ou
     * fermé ne sert plus ses fournisseurs.
     */
    public static function allowsProvider(Provider $provider): bool
    {
        $provider->loadMissing('organization');
        $status = $provider->organization?->status;

        return $status !== null && ! in_array($status, self::CLOSED, true);
    }

    /** @return list<string> */
    public static function closedStatuses(): array
    {
        return array_map(static fn (OrganizationStatus $status): string => $status->value, self::CLOSED);
    }
}
