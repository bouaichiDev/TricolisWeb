<?php

declare(strict_types=1);

namespace App\Modules\Communications\Exceptions;

use App\Modules\Communications\Enums\CommunicationStatus;
use RuntimeException;

/**
 * Refus lié au statut d'une communication.
 *
 * Traduit en 409. Une communication qui a quitté le brouillon est un document
 * de ce qui part ou est parti : son contenu, ses pièces jointes et son existence
 * même sont figés.
 */
final class CommunicationNotEditable extends RuntimeException
{
    public static function forEdition(CommunicationStatus $status): self
    {
        return new self(
            "Une communication au statut « {$status->label()} » ne peut plus être modifiée : seul un brouillon l’est.",
        );
    }

    public static function forDeletion(CommunicationStatus $status): self
    {
        return new self(
            "Une communication au statut « {$status->label()} » ne peut pas être supprimée : elle fait partie de l’historique.",
        );
    }

    public static function forAttachment(CommunicationStatus $status): self
    {
        return new self(
            "Les pièces jointes d’une communication au statut « {$status->label()} » ne peuvent plus être modifiées.",
        );
    }

    public static function forTransition(CommunicationStatus $from, CommunicationStatus $to): self
    {
        return new self(
            "Transition impossible : une communication « {$from->label()} » ne peut pas passer à « {$to->label()} ».",
        );
    }

    public static function duplicateAttachment(): self
    {
        return new self('Ce document est déjà joint à cette communication.');
    }
}
