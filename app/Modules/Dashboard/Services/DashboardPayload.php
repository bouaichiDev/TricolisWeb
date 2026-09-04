<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

/**
 * Les six formes de donnée qu'un widget peut porter.
 *
 * Le type du widget les annonce, ces méthodes les produisent, et
 * `DashboardWidgetData` les relit côté frontend. Les écrire à la main dans huit
 * sources aurait suffi à ce que l'une renvoie `values` là où les autres
 * renvoient `series` — et le composant correspondant n'aurait rien affiché,
 * sans erreur.
 *
 * Aucune de ces formes ne transporte de libellé traduit. Le serveur rend un
 * **code** — un statut, une devise, une clé i18n — et l'interface le nomme dans
 * la langue de qui regarde. Un libellé français calculé ici aurait figé le
 * tableau de bord dans une seule langue.
 */
final readonly class DashboardPayload
{
    /**
     * Un chiffre. `null` dit « pas de valeur », ce qui n'est pas zéro.
     *
     * @return array<string, mixed>
     */
    public static function kpi(int|float|null $value, ?string $unit = null): array
    {
        return ['value' => $value, 'unit' => $unit];
    }

    /**
     * Un compte qui appelle une action. Zéro reste une réponse valide, et se
     * rend sobrement : « rien à traiter » est une bonne nouvelle, pas un vide.
     *
     * @return array<string, mixed>
     */
    public static function alert(int $count): array
    {
        return ['value' => $count];
    }

    /**
     * Une **répartition** : des parts d'un même tout, qui s'additionnent.
     *
     * Deux façons de nommer les codes, et une seule s'applique à la fois :
     *
     * - `source` désigne l'entité au **référentiel des statuts**. Le frontend y
     *   lit le libellé qu'un administrateur a pu régler, plutôt qu'une
     *   traduction livrée qui l'ignorerait ;
     * - `labels` désigne un **espace de traduction** — `orderSources`,
     *   `communicationChannels`. Ces codes-là viennent d'énumérations PHP :
     *   personne ne les renomme, et le référentiel ne les connaît pas.
     *
     * Les deux `null` valent « le code se nomme lui-même », ce qui est le cas
     * d'une devise.
     *
     * @param  array<int, array{code: string, value: int|float}>  $series
     * @return array<string, mixed>
     */
    public static function chart(array $series, ?string $source = null, ?string $labels = null): array
    {
        return [
            'mode' => 'share',
            'source' => $source,
            'labels' => $labels,
            'series' => array_values($series),
        ];
    }

    /**
     * La même répartition, **vue de face** : peu de parts, lues d'un coup d'œil.
     *
     * La forme de la donnée est identique à `chart()`, et c'est le **type du
     * widget** qui choisit le rendu — pas un drapeau ici. Le camembert n'est
     * pas une variante de la barre : c'est une décision de catalogue, prise une
     * fois, sur le nombre de parts que la série peut atteindre.
     *
     * @param  array<int, array{code: string, value: int|float}>  $series
     * @return array<string, mixed>
     */
    public static function donut(array $series, ?string $source = null, ?string $labels = null): array
    {
        return self::chart($series, $source, $labels);
    }

    /**
     * **Un rapport**, et son tout.
     *
     * Le pourcentage n'est pas calculé ici : la part et le tout partent tous
     * deux, et l'interface les affiche l'un et l'autre. Un taux seul se retient
     * mal — « 72 % » ne dit pas si l'on parle de neuf cas sur douze ou de neuf
     * cents sur mille deux cents, et la première mérite un haussement d'épaules
     * quand la seconde mérite une réunion.
     *
     * Un tout à zéro est rendu tel quel, sans division : c'est au frontend de
     * dire « rien à mesurer », pas au serveur d'inventer 0 % — qui se lirait
     * comme un échec là où il n'y a simplement rien.
     *
     * @return array<string, mixed>
     */
    public static function gauge(int|float $value, int|float $total): array
    {
        return ['value' => $value, 'total' => $total];
    }

    /**
     * Le **temps**, en jours.
     *
     * Deux tableaux alignés plutôt qu'une liste de points : `buckets` porte les
     * jours, `series` porte une suite de valeurs de même longueur par série.
     * Une liste de `{date, valeurs}` aurait demandé au frontend de retrouver
     * quelles séries existent, et de gérer celles qui manquent à certains
     * jours — ici, l'alignement est garanti à la construction.
     *
     * Les jours creux valent **zéro**, jamais rien : un `GROUP BY` ne rend que
     * les jours actifs, et les enchaîner tels quels rapprocherait un lundi d'un
     * vendredi comme s'ils se suivaient. `DailySeries` s'en charge.
     *
     * @param  array<int, string>  $buckets
     * @param  array<int, array{code: string, values: array<int, int|float>}>  $series
     * @return array<string, mixed>
     */
    public static function timeseries(
        array $buckets,
        array $series,
        ?string $source = null,
        ?string $labels = null,
    ): array {
        return [
            'buckets' => array_values($buckets),
            'series' => array_values($series),
            'source' => $source,
            'labels' => $labels,
        ];
    }

    /**
     * Des montants qui **ne se comparent pas** — une devise par ligne.
     *
     * Même forme que `chart()`, et c'est justement pourquoi le mode doit être
     * dit : sans lui, le frontend dessinerait des barres proportionnelles, et
     * une barre deux fois plus longue affirmerait qu'on a facturé deux fois
     * plus. Or 5 000 CHF et 5 000 MAD ne se rangent pas sur la même règle. Le
     * total toutes devises confondues est déjà exclu ; le suggérer par une
     * longueur le serait tout autant.
     *
     * @param  array<int, array{code: string, value: int|float}>  $series
     * @return array<string, mixed>
     */
    public static function amounts(array $series): array
    {
        return [
            'mode' => 'amounts',
            'source' => null,
            'labels' => null,
            'series' => array_values($series),
        ];
    }

    /**
     * Quelques lignes, jamais toutes.
     *
     * Le nombre est borné par la requête, pas ici : charger cent lignes pour en
     * afficher cinq coûterait autant que si on les affichait.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public static function list(array $items): array
    {
        return ['items' => array_values($items)];
    }
}
