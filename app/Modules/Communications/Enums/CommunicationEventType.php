<?php

declare(strict_types=1);

namespace App\Modules\Communications\Enums;

/**
 * Événements métier déclencheurs d'une règle — onze valeurs, closes.
 *
 * Aucun de ces événements n'est émis par les Phases 1 à 8 : le projet n'a ni
 * `Event` Laravel ni listener. Les règles sont donc enregistrées et évaluables,
 * mais leur déclenchement automatique attend que ces événements existent. Le
 * §2 interdit d'en inventer la sémantique.
 */
enum CommunicationEventType: string
{
    case ORDER_CREATED = 'order_created';
    case ORDER_CONFIRMED = 'order_confirmed';
    case ORDER_CANCELLED = 'order_cancelled';
    case SERVICE_PLANNED = 'service_planned';
    case APPOINTMENT_REQUESTED = 'appointment_requested';
    case APPOINTMENT_CONFIRMED = 'appointment_confirmed';
    case DRIVER_ASSIGNED = 'driver_assigned';
    case TOUR_STOP_APPROACHING = 'tour_stop_approaching';
    case SERVICE_COMPLETED = 'service_completed';
    case POD_CREATED = 'pod_created';
    case CLAIM_CREATED = 'claim_created';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'Commande créée',
            self::ORDER_CONFIRMED => 'Commande confirmée',
            self::ORDER_CANCELLED => 'Commande annulée',
            self::SERVICE_PLANNED => 'Service planifié',
            self::APPOINTMENT_REQUESTED => 'Rendez-vous demandé',
            self::APPOINTMENT_CONFIRMED => 'Rendez-vous confirmé',
            self::DRIVER_ASSIGNED => 'Chauffeur affecté',
            self::TOUR_STOP_APPROACHING => 'Arrêt imminent',
            self::SERVICE_COMPLETED => 'Service terminé',
            self::POD_CREATED => 'Preuve de livraison créée',
            self::CLAIM_CREATED => 'Réclamation créée',
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
