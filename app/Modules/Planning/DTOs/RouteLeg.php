<?php

declare(strict_types=1);

namespace App\Modules\Planning\DTOs;

/**
 * Un segment d'itinéraire : d'un point au suivant.
 *
 * **Mètres et secondes**, tels que le service les rend. La conversion en
 * minutes se fait au dernier moment, là où une colonne l'exige : convertir tôt
 * ferait perdre la précision segment après segment, et un total en minutes
 * arrondies dérive de plusieurs minutes sur une tournée de vingt arrêts.
 */
final readonly class RouteLeg
{
    public function __construct(
        public int $distanceMeters,
        public int $travelSeconds,
        /** Durée avec trafic, quand le service la distingue. */
        public ?int $trafficSeconds = null,
        /** Durée sans trafic, quand le service la distingue. */
        public ?int $baseSeconds = null,
    ) {}

    /**
     * Minutes de conduite, arrondies au supérieur.
     *
     * Une tournée annoncée plus courte qu'elle ne l'est fait rater des
     * rendez-vous ; l'arrondi va donc vers le haut.
     */
    public function travelMinutes(): int
    {
        return (int) ceil($this->travelSeconds / 60);
    }
}
