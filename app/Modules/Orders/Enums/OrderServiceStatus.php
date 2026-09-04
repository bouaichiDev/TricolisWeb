<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

/**
 * Le cycle d'une prestation, de sa saisie à sa facture.
 *
 * **Un seul axe, et non deux.** Une seconde liste avait été créée sur la source
 * `service` — le catalogue des prestations — avec le vocabulaire du terrain :
 * « en route », « effectué », « client ne répond pas ». Mais un catalogue ne
 * roule pas : « Livraison » en tant que type est actif ou inactif, c'est la
 * prestation posée sur une commande qui traverse ces états. Les deux listes
 * décrivaient donc la même chose au même endroit, et aucune n'était lue.
 *
 * Le vocabulaire du terrain vit désormais ici, dans les **libellés** : le code
 * `in_progress` reste ce sur quoi le code s'appuie, « En route » est ce que
 * l'écran montre. Le référentiel sépare les deux depuis toujours ; il n'avait
 * simplement jamais reçu de libellé français, d'où les `DRAFT` et `PENDING`
 * affichés en majuscules.
 */
enum OrderServiceStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case READY_TO_PLAN = 'ready_to_plan';
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    /**
     * Le client n'a pas répondu : la prestation n'est ni faite ni ratée.
     *
     * Distinct de `FAILED`, qui dit que quelque chose a échoué de notre côté.
     * Ici tout s'est bien passé jusqu'à la porte — c'est une tentative à
     * refaire, pas un incident à traiter, et les confondre fausserait autant le
     * décompte des échecs que celui des livraisons.
     */
    case CUSTOMER_NO_RESPONSE = 'customer_no_response';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case INVOICED = 'invoiced';

    /** Ce que l'écran montre — le code, lui, ne bouge pas. */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::PENDING => 'En attente',
            self::READY_TO_PLAN => 'Prêt à planifier',
            self::PLANNED => 'Planifié',
            self::IN_PROGRESS => 'En route',
            self::COMPLETED => 'Effectué',
            self::CUSTOMER_NO_RESPONSE => 'Client ne répond pas',
            self::FAILED => 'Échec',
            self::CANCELLED => 'Annulé',
            self::INVOICED => 'Facturé',
        };
    }

    /**
     * Une tentative infructueuse se justifie.
     *
     * « Client ne répond pas » sans motif ne dit pas s'il faut rappeler,
     * repasser demain ou rendre la marchandise.
     */
    public function requiresReason(): bool
    {
        return $this === self::CUSTOMER_NO_RESPONSE || $this === self::FAILED;
    }
}
