<?php

declare(strict_types=1);

namespace App\Modules\Tours\Enums;

/**
 * Statut d'un arrêt de tournée.
 *
 * Exactement les six valeurs du diagramme (lignes 61-68). `PLANNED`, `FAILED`,
 * `DELAYED`, `DEPARTED` et `CLOSED` ne figurent pas au modèle et ne sont pas
 * ajoutés.
 */
enum TourStopStatus: string
{
    case PENDING = 'pending';
    case ARRIVED = 'arrived';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::ARRIVED => 'Arrivé',
            self::IN_PROGRESS => 'En cours',
            self::COMPLETED => 'Terminé',
            self::SKIPPED => 'Passé',
            self::CANCELLED => 'Annulé',
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
