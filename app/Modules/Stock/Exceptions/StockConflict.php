<?php

declare(strict_types=1);

namespace App\Modules\Stock\Exceptions;

use RuntimeException;

/**
 * Conflits métier du stock, traduits en 409 par les contrôleurs.
 */
final class StockConflict extends RuntimeException
{
    public static function insufficientAvailability(string $available, string $requested): self
    {
        return new self("Stock disponible insuffisant : {$available} disponible, {$requested} demandé.");
    }

    public static function insufficientQuantity(): self
    {
        return new self('Le mouvement rendrait la quantité négative.');
    }

    public static function insufficientReservation(): self
    {
        return new self('La quantité réservée ne peut pas devenir négative.');
    }

    public static function reservedExceedsQuantity(): self
    {
        return new self('La quantité réservée ne peut pas dépasser la quantité en stock.');
    }

    public static function alreadyReleased(): self
    {
        return new self('Cette réservation est déjà libérée.');
    }

    public static function itemInUse(): self
    {
        return new self('Impossible de supprimer un article qui porte du stock, un mouvement ou une réservation.');
    }

    public static function locationInUse(): self
    {
        return new self('Impossible de supprimer un emplacement qui porte des enfants, du stock ou une réservation active.');
    }
}
