<?php

declare(strict_types=1);

namespace App\Modules\Communications\Enums;

/**
 * Cycle de vie d'une communication — neuf valeurs, closes.
 *
 * Les transitions sont portées par l'enum, comme `OrderStatus` en Phase 3 et
 * `TourStatus` en Phase 4 : c'est le moteur de statut du projet, réutilisé et
 * non redéveloppé (§24).
 */
enum CommunicationStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SCHEDULED => 'Programmée',
            self::QUEUED => 'En file',
            self::SENDING => 'En cours d’envoi',
            self::SENT => 'Envoyée',
            self::DELIVERED => 'Remise',
            self::READ => 'Lue',
            self::FAILED => 'Échouée',
            self::CANCELLED => 'Annulée',
        };
    }

    /**
     * Transitions autorisées depuis ce statut.
     *
     * `SENT → FAILED` est permis : un transporteur peut signaler un rejet après
     * acceptation. `SENT → READ` sans `DELIVERED` l'est aussi : tous les canaux
     * ne signalent pas la remise. `FAILED → QUEUED` est la relance, seule sortie
     * d'un échec.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::SCHEDULED, self::QUEUED, self::CANCELLED],
            self::SCHEDULED => [self::DRAFT, self::QUEUED, self::CANCELLED],
            self::QUEUED => [self::SENDING, self::FAILED, self::CANCELLED],
            self::SENDING => [self::SENT, self::FAILED],
            self::SENT => [self::DELIVERED, self::READ, self::FAILED],
            self::DELIVERED => [self::READ],
            self::FAILED => [self::QUEUED],
            self::READ, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Le contenu accepte-t-il encore des modifications ?
     *
     * Le brouillon et la communication programmée sont modifiables : rien n'est
     * encore parti, et retirer la date de programmation ramène au brouillon.
     * Dès la mise en file, le contenu est un snapshot de ce qui part ou est
     * parti : le modifier réécrirait l'histoire.
     */
    public function allowsContentChanges(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED], true);
    }

    /**
     * La communication peut-elle encore être supprimée ?
     *
     * Le §42 réserve la suppression au brouillon : une communication engagée est
     * une donnée historique.
     */
    public function allowsDeletion(): bool
    {
        return $this === self::DRAFT;
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
