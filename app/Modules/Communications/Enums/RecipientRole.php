<?php

declare(strict_types=1);

namespace App\Modules\Communications\Enums;

use App\Shared\Enums\ContactRole;

/**
 * Rôle du destinataire d'une communication — six valeurs, closes.
 *
 * Trois de ces rôles désignent un contact de commande. Plutôt qu'une seconde
 * nomenclature, ils sont mis en correspondance avec le `ContactRole` du Shared,
 * déjà utilisé par `OrderServiceContact` depuis la Phase 3.
 */
enum RecipientRole: string
{
    case CUSTOMER = 'customer';
    case LOAD_CONTACT = 'load_contact';
    case DELIVERY_CONTACT = 'delivery_contact';
    case BILLING_CONTACT = 'billing_contact';
    case INTERNAL_USER = 'internal_user';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Client',
            self::LOAD_CONTACT => 'Contact de chargement',
            self::DELIVERY_CONTACT => 'Contact de livraison',
            self::BILLING_CONTACT => 'Contact de facturation',
            self::INTERNAL_USER => 'Utilisateur interne',
            self::CUSTOM => 'Destinataire libre',
        };
    }

    /**
     * Rôle de contact de commande correspondant, s'il en existe un.
     */
    public function contactRole(): ?ContactRole
    {
        return match ($this) {
            self::LOAD_CONTACT => ContactRole::LOAD,
            self::DELIVERY_CONTACT => ContactRole::DELIVERY,
            self::BILLING_CONTACT => ContactRole::BILLING,
            default => null,
        };
    }

    /**
     * Le destinataire est-il fourni par l'appelant ?
     *
     * Pour les cinq autres rôles, les coordonnées sont déduites de la commande :
     * accepter celles du payload rendrait `recipientRole` décoratif.
     */
    public function isExplicit(): bool
    {
        return $this === self::CUSTOM;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
