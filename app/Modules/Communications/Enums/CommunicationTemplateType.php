<?php

declare(strict_types=1);

namespace App\Modules\Communications\Enums;

/**
 * Nature métier d'un template — douze valeurs, closes.
 *
 * Sert aussi de `communicationType` sur `OrderCommunication` : le diagramme
 * réutilise le même enum pour les deux, aucun second type n'est créé.
 */
enum CommunicationTemplateType: string
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
            self::CUSTOM => 'Personnalisé',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
