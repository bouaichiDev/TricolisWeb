<?php

declare(strict_types=1);

namespace App\Modules\Tours\Enums;

/**
 * Statut d'une tournée.
 *
 * Exactement les six valeurs du diagramme (lignes 52-59), ni plus ni moins.
 *
 * Aucune transition n'est définie : le diagramme n'en énumère aucune, et le §21
 * interdit d'en inventer. La validation se limite donc à l'appartenance à
 * l'enum. Le jour où les transitions seront arrêtées, elles s'ajouteront ici
 * sur le modèle d'`OrderStatus`.
 */
enum TourStatus: string
{
    case DRAFT = 'draft';
    case PLANNED = 'planned';
    case CONFIRMED = 'confirmed';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::PLANNED => 'Planifiée',
            self::CONFIRMED => 'Confirmée',
            self::IN_PROGRESS => 'En cours',
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
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
