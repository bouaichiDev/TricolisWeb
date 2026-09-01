<?php

declare(strict_types=1);

namespace App\Modules\Templates\Enums;

/**
 * Nature métier d'un modèle — treize valeurs, closes.
 *
 * Sert aussi de `communicationType` sur `OrderCommunication` : le diagramme
 * réutilise le même enum pour les deux, aucun second type n'est créé.
 *
 * `INVOICE` est entré ici plutôt que dans un `InvoiceTemplateType` séparé. Un
 * modèle de facture se résout, se rend et se versionne exactement comme un
 * modèle de message ; deux énumérations auraient forcé chaque écran et chaque
 * requête à tester laquelle des deux lire.
 */
enum TemplateType: string
{
    case APPOINTMENT_REQUEST = 'appointment_request';
    case APPOINTMENT_CONFIRMATION = 'appointment_confirmation';
    case APPOINTMENT_REMINDER = 'appointment_reminder';
    case DRIVER_ASSIGNED = 'driver_assigned';
    case DRIVER_DEPARTED = 'driver_departed';
    case ARRIVAL_ESTIMATE = 'arrival_estimate';
    case ARRIVAL_SOON = 'arrival_soon';
    case DELIVERY_CONFIRMATION = 'delivery_confirmation';
    case DELIVERY_FAILED = 'delivery_failed';
    case POD_AVAILABLE = 'pod_available';
    case ORDER_CANCELLED = 'order_cancelled';
    case INVOICE = 'invoice';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::APPOINTMENT_REQUEST => 'Demande de rendez-vous',
            self::APPOINTMENT_CONFIRMATION => 'Confirmation de rendez-vous',
            self::APPOINTMENT_REMINDER => 'Rappel de rendez-vous',
            self::DRIVER_ASSIGNED => 'Chauffeur affecté',
            self::DRIVER_DEPARTED => 'Chauffeur parti',
            self::ARRIVAL_ESTIMATE => 'Estimation d’arrivée',
            self::ARRIVAL_SOON => 'Arrivée imminente',
            self::DELIVERY_CONFIRMATION => 'Confirmation de livraison',
            self::DELIVERY_FAILED => 'Échec de livraison',
            self::POD_AVAILABLE => 'Preuve de livraison disponible',
            self::ORDER_CANCELLED => 'Commande annulée',
            self::INVOICE => 'Facture',
            self::CUSTOM => 'Personnalisé',
        };
    }

    /**
     * Un document, par opposition à un message.
     *
     * Une facture n'a ni canal, ni destinataire, ni objet : elle se rend et se
     * classe. C'est ce qui justifie que `channel` soit facultatif sur la table.
     */
    public function isDocument(): bool
    {
        return $this === self::INVOICE;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
