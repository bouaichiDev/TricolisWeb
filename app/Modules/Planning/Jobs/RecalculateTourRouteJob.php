<?php

declare(strict_types=1);

namespace App\Modules\Planning\Jobs;

use App\Modules\Planning\Actions\RecalculateTourRoute;
use App\Modules\Tours\Models\Tour;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recalcule l'itinéraire d'une tournée, en file d'attente.
 *
 * **En file, et non pendant le glisser.** Une tournée de douze arrêts demande
 * onze appels distants : les attendre ferait durer un dépôt plusieurs secondes,
 * quand le §90 demande justement de ne pas recalculer à chaque mouvement. La
 * distance apparaît un instant après, l'écran affichant « non calculé » entre
 * les deux.
 *
 * Le Job ne porte que l'identifiant : recharger la tournée à l'exécution
 * garantit qu'il calcule l'ordre courant. Deux dépôts rapprochés produisent
 * deux Jobs, et le second recalcule tout — le résultat est le même que si le
 * premier n'avait pas eu lieu.
 */
class RecalculateTourRouteJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $tourId) {}

    public function handle(RecalculateTourRoute $route): void
    {
        $tour = Tour::find($this->tourId);

        if ($tour === null) {
            return;
        }

        $route->execute($tour);
    }
}
