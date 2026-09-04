<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;

/**
 * Décide sur quel arrêt un service atterrit.
 *
 * **Un arrêt, c'est un lieu à une date pour un créneau tenable.** Trois
 * services à la même adresse le même jour ne font qu'un arrêt : le camion s'y
 * range une fois. Mais deux chargements à la même adresse, l'un le matin,
 * l'autre l'après-midi, en font deux — il faudra bien y revenir.
 *
 * **La clé ne dit pas tout.** `grouping_key` réunit la famille — adresse et
 * date — mais la compatibilité des créneaux ne s'exprime pas dans une égalité :
 * deux créneaux se chevauchent ou non, ce qui se compare deux à deux. Plusieurs
 * arrêts peuvent donc porter la même clé, et c'est voulu.
 *
 * La clé reste lisible plutôt que hachée : quand une tournée surprend, on lit
 * la ligne.
 */
final readonly class StopGrouping
{
    /** Marque les arrêts nés de la planification, par opposition à une saisie. */
    public const string GENERATION_AUTOMATIC = 'automatic';

    /** Statut d'un arrêt qui vient d'être créé. */
    private const string INITIAL_STATUS = 'pending';

    /**
     * Clé de regroupement : le lieu et le jour.
     *
     * La date vient de `requested_date`, qui porte le jour convenu avec le
     * client — pas la date de la tournée, qui pourrait en différer.
     */
    public function keyFor(OrderService $service): string
    {
        $date = $service->requested_date?->toDateString() ?? 'sans-date';

        return sprintf('address:%s|date:%s', $service->address_id, $date);
    }

    /**
     * Arrêt d'accueil, existant ou nouveau.
     *
     * Les arrêts déjà porteurs de la même clé sont examinés dans l'ordre ; le
     * premier dont les créneaux tiennent avec celui du service l'emporte. Sinon
     * un arrêt naît, avec la même clé.
     */
    public function stopFor(Tour $tour, OrderService $service): TourStop
    {
        $key = $this->keyFor($service);

        $candidates = TourStop::where('tour_id', $tour->id)
            ->where('grouping_key', $key)
            ->orderBy('sequence')
            ->get();

        foreach ($candidates as $stop) {
            if ($this->accepts($stop, $service)) {
                return $stop;
            }
        }

        return TourStop::create([
            'tour_id' => $tour->id,
            'address_id' => $service->address_id,
            'sequence' => $this->nextSequence($tour),
            'grouping_key' => $key,
            'generation_mode' => self::GENERATION_AUTOMATIC,
            'status' => self::INITIAL_STATUS,
        ]);
    }

    /**
     * Cet arrêt peut-il encore accueillir ce service ?
     *
     * Un service sans créneau s'accommode de tout. Deux créneaux qui ne se
     * chevauchent pas ne peuvent pas être servis au même passage.
     */
    public function accepts(TourStop $stop, OrderService $service): bool
    {
        if ($service->requested_from === null || $service->requested_to === null) {
            return true;
        }

        $existing = OrderService::whereIn(
            'id',
            $stop->services()->where('is_active_assignment', true)->pluck('order_service_id'),
        )->get();

        foreach ($existing as $other) {
            if ($other->requested_from === null || $other->requested_to === null) {
                continue;
            }

            if ($other->requested_from > $service->requested_to
                || $other->requested_to < $service->requested_from) {
                return false;
            }
        }

        return true;
    }

    private function nextSequence(Tour $tour): int
    {
        return (int) TourStop::where('tour_id', $tour->id)->max('sequence') + 1;
    }
}
