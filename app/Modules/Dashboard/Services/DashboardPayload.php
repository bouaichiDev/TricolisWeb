<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

/**
 * Les quatre formes de donnée qu'un widget peut porter.
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
     * Des barres, et de quoi les nommer.
     *
     * `source` désigne l'entité au référentiel des statuts : le frontend y lit
     * le libellé qu'un administrateur a pu régler, plutôt qu'une traduction
     * livrée qui l'ignorerait. `null` pour une série qui n'est pas un statut —
     * les devises, par exemple, se nomment d'elles-mêmes.
     *
     * @param  array<int, array{code: string, value: int|float}>  $series
     * @return array<string, mixed>
     */
    public static function chart(array $series, ?string $source = null): array
    {
        return ['source' => $source, 'series' => array_values($series)];
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
