<?php

declare(strict_types=1);

namespace App\Shared\Dashboard;

/**
 * Ce qu'une part d'un graphe emporte vers la liste qu'elle ouvre.
 *
 * Un widget porte déjà `route` — l'écran de destination. Cela suffisait tant
 * que la carte entière était le lien : on cliquait « Commandes par jour », on
 * arrivait sur les commandes. Mais cliquer **une colonne** et retomber sur la
 * liste entière est une promesse trahie : la colonne désigne un jour, la barre
 * désigne un statut, et la liste sait filtrer sur les deux.
 *
 * Ce descripteur dit **sous quels noms** la liste attend ces valeurs. Rien de
 * plus : ni la valeur elle-même, qui vient de ce qu'on a cliqué, ni la façon
 * de construire l'URL, qui est l'affaire du frontend.
 *
 * Les noms sont ceux du `FormRequest` de la liste visée — `status`, `source`,
 * `createdFrom`. Les écrire ici plutôt que dans le composant garde la même
 * ligne de partage que `route` : le catalogue connaît la destination, et une
 * destination sans ses filtres n'est qu'une demi-destination. Un nom de
 * paramètre qui ne correspond à rien serait ignoré par la liste — d'où le test
 * de cohérence qui les confronte aux règles de validation réelles.
 *
 * Tout est facultatif, et l'absence a un sens : un graphe de statuts n'a pas de
 * jour à transmettre, une courbe de tendance n'a pas de série qui soit un
 * filtre — « créées » et « achevées » ne sont pas des valeurs de colonne.
 */
final readonly class DashboardDrilldown
{
    public function __construct(
        /** Paramètre qui reçoit le code de la série cliquée — `status`, `source`. */
        public ?string $seriesParam = null,
        /** Paramètres qui reçoivent les bornes du jour cliqué, ou de la période. */
        public ?string $fromParam = null,
        public ?string $toParam = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'seriesParam' => $this->seriesParam,
            'fromParam' => $this->fromParam,
            'toParam' => $this->toParam,
        ];
    }
}
