<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStopService;
use Illuminate\Support\Facades\DB;

/**
 * Qui compose une tournée en ce moment.
 *
 * **Distinct du créateur.** Le §22 réserve un brouillon à celui qui l'a créé ;
 * cette réservation-ci est plus courte et plus explicite : elle se prend au
 * premier geste de planification et se rend quand on a fini. Elle est devenue
 * nécessaire le 28 août 2026, quand le propriétaire du projet a demandé que
 * confirmer ses modifications **ne change pas le statut** : la fin de
 * l'exclusivité ne pouvait donc plus se déduire de la sortie du brouillon.
 *
 * Elle ne vaut que sur un brouillon. Une tournée confirmée suit les permissions
 * ordinaires : la réserver n'aurait plus de sens, et l'oublier bloquerait une
 * tournée réelle.
 */
final readonly class TourReservation
{
    /**
     * Réserve la tournée pour cet utilisateur, si elle est libre.
     *
     * Prise sous verrou de ligne : deux planificateurs qui cliquent au même
     * instant ne doivent pas la croire tous deux à eux.
     *
     * @return bool vrai si l'utilisateur la tient à la sortie
     */
    public function acquire(Tour $tour, string $userId): bool
    {
        if ($tour->status !== TourStatus::DRAFT) {
            return true;
        }

        return DB::transaction(function () use ($tour, $userId): bool {
            $locked = Tour::whereKey($tour->id)->lockForUpdate()->first();

            if ($locked === null) {
                return false;
            }

            if ($locked->locked_by !== null && $locked->locked_by !== $userId) {
                return false;
            }

            if ($locked->locked_by === null) {
                $locked->forceFill(['locked_by' => $userId, 'locked_at' => now()])->save();
                $tour->forceFill(['locked_by' => $userId, 'locked_at' => $locked->locked_at]);
            }

            return true;
        });
    }

    /**
     * Rend la tournée.
     *
     * Sans condition sur le statut : une tournée qui a quitté le brouillon
     * pendant qu'on la tenait doit pouvoir se libérer quand même, sinon la
     * réservation survivrait à ce qu'elle protégeait.
     */
    public function release(Tour $tour): void
    {
        DB::transaction(function () use ($tour): void {
            // Ce qui attendait devient acquis : c'est ce geste, et lui seul, qui
            // rend la composition visible aux autres ecrans.
            TourStopService::whereNull('confirmed_at')
                ->whereHas('tourStop', fn ($stop) => $stop->where('tour_id', $tour->id))
                ->update(['confirmed_at' => now()]);

            $tour->forceFill(['locked_by' => null, 'locked_at' => null])->save();
        });
    }

    /** Cet utilisateur peut-il composer cette tournée ? */
    public function allows(Tour $tour, string $userId): bool
    {
        if ($tour->status !== TourStatus::DRAFT) {
            return true;
        }

        return $tour->locked_by === null || $tour->locked_by === $userId;
    }

    /**
     * Qui tient chacune de ces tournées, nommé.
     *
     * En une requête pour toute une page : la liste ne doit pas poser la
     * question ligne par ligne.
     *
     * @param  iterable<Tour>  $tours
     * @return array<string, array{id: string, name: string}> par tournée
     */
    public function holdersOf(iterable $tours): array
    {
        $byTour = [];

        foreach ($tours as $tour) {
            if ($tour->locked_by !== null) {
                $byTour[$tour->id] = $tour->locked_by;
            }
        }

        if ($byTour === []) {
            return [];
        }

        $names = User::whereIn('id', array_values(array_unique($byTour)))
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn (User $user): array => [
                $user->id => trim($user->first_name.' '.$user->last_name),
            ]);

        $holders = [];

        foreach ($byTour as $tourId => $userId) {
            $holders[$tourId] = ['id' => $userId, 'name' => $names[$userId] ?? $userId];
        }

        return $holders;
    }
}
