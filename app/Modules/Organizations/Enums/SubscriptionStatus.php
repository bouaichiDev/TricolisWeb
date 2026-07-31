<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Enums;

/**
 * Statuts d'un abonnement.
 *
 * Le diagramme partagé déclare `Subscription.status` comme une chaîne libre sans
 * énumérer ses valeurs. Cette liste est donc une hypothèse documentée (§12.A de
 * `conception-analysis.md`) : la colonne reste un `VARCHAR`, ce qui permet
 * d'ajouter une valeur sans migration si le métier en fait apparaître une.
 */
enum SubscriptionStatus: string
{
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::TRIALING => 'Période d’essai',
            self::ACTIVE => 'Actif',
            self::SUSPENDED => 'Suspendu',
            self::CANCELLED => 'Résilié',
            self::EXPIRED => 'Expiré',
        };
    }

    /**
     * Un abonnement dans cet état donne-t-il accès à la plateforme ?
     */
    public function grantsAccess(): bool
    {
        return match ($this) {
            self::TRIALING, self::ACTIVE => true,
            self::SUSPENDED, self::CANCELLED, self::EXPIRED => false,
        };
    }
}
