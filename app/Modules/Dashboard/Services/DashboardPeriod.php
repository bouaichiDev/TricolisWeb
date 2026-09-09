<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use Carbon\CarbonImmutable;

/**
 * La période que l'utilisateur a choisie, quand il en a choisi une.
 *
 * Le tableau de bord répond d'abord à « où en est-on », et cela n'a pas de
 * date : une commande à planifier l'est aujourd'hui, pas la semaine dernière.
 * Mais la moitié des cartes portent bel et bien un intervalle — un volume par
 * jour, une répartition, les six dernières lignes — et ces cartes-là ne
 * savaient répondre qu'à « depuis toujours » ou « sur les quatorze derniers
 * jours ». Demander le mois d'août obligeait à ouvrir la liste et à refaire le
 * filtre à la main.
 *
 * **Toutes les cartes ne la suivent pas**, et c'est le point délicat : un
 * compteur « Commandes du jour » filtré sur septembre afficherait un total de
 * mois sous un titre qui dit le jour. Le catalogue tranche donc widget par
 * widget, par `periodAware`, et `DashboardDataSources` fait en sorte qu'une
 * source ne **puisse pas** voir la période pour un widget qui ne l'a pas
 * déclarée : elle est appelée deux fois, avec deux contextes.
 *
 * Les bornes sont inclusives des deux côtés : « du 1er au 30 » compte le 30.
 * Un intervalle ouvert à droite se serait lu « jusqu'au 30 » en excluant la
 * journée du 30, et personne ne lit une date de fin de cette façon.
 */
final readonly class DashboardPeriod
{
    /**
     * Un trimestre, et pas davantage.
     *
     * Les graphes temporels tracent **une colonne par jour** : une année en
     * demanderait trois cent soixante-cinq, larges de deux pixels, et remplirait
     * la réponse de séries que personne ne peut lire. La borne est donc posée
     * ici plutôt que d'être découverte à l'affichage, et la validation la
     * renvoie en 422 avec son motif.
     */
    public const int MAX_DAYS = 92;

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    /**
     * Deux dates du jour, telles que la requête les a validées.
     *
     * `null` dès que l'une manque : une période à moitié saisie n'est pas une
     * période, et lui inventer une borne — aujourd'hui, le début du mois —
     * aurait filtré sur un intervalle que personne n'a demandé.
     */
    public static function fromDates(?string $from, ?string $to): ?self
    {
        if ($from === null || $to === null || $from === '' || $to === '') {
            return null;
        }

        return new self(
            CarbonImmutable::parse($from)->startOfDay(),
            CarbonImmutable::parse($to)->startOfDay(),
        );
    }

    /**
     * Bornes telles qu'un `whereBetween` les attend.
     *
     * La fin porte l'heure de fin de journée : comparée à minuit, une colonne
     * horodatée aurait écarté tout ce qui s'est passé le dernier jour — le seul
     * qui intéresse quand on filtre sur une date unique.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bounds(): array
    {
        return [$this->from, $this->to->endOfDay()];
    }

    /** Nombre de jours couverts, bornes comprises. */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }
}
