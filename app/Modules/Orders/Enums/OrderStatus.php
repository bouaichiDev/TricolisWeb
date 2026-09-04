<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

enum OrderStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case READY = 'ready';
    case PARTIALLY_PLANNED = 'partially_planned';
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case PARTIALLY_INVOICED = 'partially_invoiced';
    case INVOICED = 'invoiced';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::CONFIRMED => 'Confirmée',
            self::READY => 'Prête',
            self::PARTIALLY_PLANNED => 'Partiellement planifiée',
            self::PLANNED => 'Planifiée',
            self::IN_PROGRESS => 'En cours',
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
            self::PARTIALLY_INVOICED => 'Partiellement facturée',
            self::INVOICED => 'Facturée',
        };
    }

    /**
     * Transitions autorisées depuis ce statut, tous modules confondus.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::READY, self::DRAFT, self::CANCELLED],
            self::READY => [self::PARTIALLY_PLANNED, self::PLANNED, self::CONFIRMED, self::CANCELLED],
            self::PARTIALLY_PLANNED => [self::PLANNED, self::READY, self::CANCELLED],
            self::PLANNED => [self::IN_PROGRESS, self::PARTIALLY_PLANNED, self::CANCELLED],
            self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED => [self::PARTIALLY_INVOICED, self::INVOICED],
            self::PARTIALLY_INVOICED => [self::INVOICED],
            self::INVOICED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Statuts qu'un opérateur peut poser lui-même à ce stade du projet.
     *
     * Les statuts de planification et de facturation sont produits par leurs
     * modules respectifs, pas saisis à la main : les exposer maintenant
     * permettrait de déclarer une commande « planifiée » sans tournée.
     *
     * @return list<self>
     */
    public static function manuallyAssignable(): array
    {
        return [self::DRAFT, self::CONFIRMED, self::READY, self::CANCELLED];
    }

    public function isManuallyAssignable(): bool
    {
        return in_array($this, self::manuallyAssignable(), true);
    }

    /**
     * Valeur de départ de « le contenu reste-t-il modifiable ? ».
     *
     * **Ce n'est plus la règle appliquée.** À l'exécution, c'est
     * `statuses.allows_content_changes` que lit `StatusMachine` : le cycle de
     * vie appartient au référentiel, que l'administrateur plateforme règle. Ce
     * qui suit ne sert qu'au semis d'une base neuve.
     *
     * L'exploitation demande à pouvoir corriger une commande déjà prête ou
     * déjà commencée — un colis ajouté au dernier moment, un article rectifié
     * sur le terrain. `PLANNED` et au-delà restent fermés : la tournée
     * construite dessus ne serait pas prévenue du changement.
     */
    public function allowsContentChanges(): bool
    {
        return in_array($this, [self::DRAFT, self::CONFIRMED, self::READY, self::IN_PROGRESS], true);
    }

    /**
     * Valeur de départ de « ce statut exige-t-il un motif ? ».
     *
     * Même remarque : le référentiel fait foi à l'exécution.
     */
    public function requiresReason(): bool
    {
        return $this === self::CANCELLED;
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
