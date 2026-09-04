<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Des lignes groupées par jour, remises en série continue.
 *
 * **Les jours sans donnée sont rendus à zéro, jamais omis.** C'est tout l'objet
 * de cette classe. Un `GROUP BY DATE(...)` ne rend que les jours où quelque
 * chose s'est passé : le graphe qui les enchaînerait tels quels rapprocherait un
 * lundi d'un vendredi comme s'ils se suivaient, et une semaine creuse
 * disparaîtrait au lieu de se voir. Un trou est une information.
 *
 * Le remplissage se fait ici et non côté frontend, pour la même raison qu'ailleurs :
 * deux façons de fabriquer les mêmes jours auraient fini par ne plus tomber sur
 * le même fuseau.
 */
final readonly class DailySeries
{
    /**
     * @param  Collection<int, object>  $rows  Lignes `{day, code, total}`.
     * @param  array<int, string>  $from  Bornes, telles que `DashboardContext::window()` les rend.
     * @return array<string, mixed>
     */
    public static function build(Collection $rows, CarbonImmutable $start, int $days): array
    {
        $buckets = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $buckets[] = $start->addDays($offset)->toDateString();
        }

        $index = array_flip($buckets);

        /** @var array<string, array<int, int|float>> $byCode */
        $byCode = [];

        foreach ($rows as $row) {
            $code = (string) $row->code;
            // La date rendue par MySQL peut porter l'heure selon la colonne ;
            // seul le jour compte, et le comparer entier manquerait la case.
            $day = substr((string) $row->day, 0, 10);

            if (! isset($index[$day])) {
                continue;
            }

            $byCode[$code] ??= array_fill(0, $days, 0);
            $byCode[$code][$index[$day]] = (int) $row->total;
        }

        // Trié par code : l'ordre des séries gouverne l'attribution des
        // couleurs, et l'ordre dans lequel SQL rend les lignes n'est pas stable
        // d'un appel à l'autre.
        ksort($byCode);

        return [
            'buckets' => $buckets,
            'series' => array_map(
                static fn (string $code): array => ['code' => $code, 'values' => $byCode[$code]],
                array_keys($byCode),
            ),
        ];
    }

    /**
     * Une seule série, nommée, à partir de comptes par jour.
     *
     * @param  Collection<int, object>  $rows  Lignes `{day, total}`.
     * @return array<int, int|float>
     */
    public static function values(Collection $rows, CarbonImmutable $start, int $days): array
    {
        $index = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $index[$start->addDays($offset)->toDateString()] = $offset;
        }

        $values = array_fill(0, $days, 0);

        foreach ($rows as $row) {
            $day = substr((string) $row->day, 0, 10);

            if (isset($index[$day])) {
                $values[$index[$day]] = (int) $row->total;
            }
        }

        return $values;
    }
}
