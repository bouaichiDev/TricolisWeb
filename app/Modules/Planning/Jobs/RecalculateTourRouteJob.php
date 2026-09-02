<?php

declare(strict_types=1);

namespace App\Modules\Planning\Jobs;

use App\Modules\Planning\Actions\RecalculateTourRoute;
use App\Modules\Planning\Services\RoutingService;
use App\Modules\Tours\Models\Tour;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recalcule l'itinéraire d'une tournée, dès la planification.
 *
 * **Pendant la requête, pas en file d'attente.** Une file demande un processus
 * qui la consomme ; sans lui, le planificateur voit ses arrêts s'aligner sans
 * jamais rien apprendre du chemin qui les sépare, et rien à l'écran ne dit que
 * le calcul attend. Un temps de route qui arrive « plus tard, peut-être » ne
 * sert pas à décider d'un ordre maintenant.
 *
 * Ce qui rendait la file nécessaire, c'était le coût : douze arrêts, onze
 * appels distants à chaque mouvement. {@see RoutingService} les met désormais
 * en cache segment par segment — ajouter un arrêt à une tournée déjà calculée
 * n'en demande plus qu'un, et réordonner n'en demande aucun s'il ne crée pas de
 * paire nouvelle.
 *
 * **`sync` et non l'exécution immédiate.** Passer par la file synchrone laisse
 * `afterCommit()` jouer son rôle : le calcul part une fois la transaction
 * écrite, jamais pendant — un appel distant de plusieurs secondes tiendrait
 * sinon les verrous ouverts tout du long.
 *
 * Le Job ne porte que l'identifiant : recharger la tournée à l'exécution
 * garantit qu'il calcule l'ordre courant. Deux dépôts rapprochés produisent
 * deux Jobs, et le second recalcule tout — le résultat est le même que si le
 * premier n'avait pas eu lieu.
 */
class RecalculateTourRouteJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $tourId)
    {
        $this->onConnection('sync');
    }

    public function handle(RecalculateTourRoute $route): void
    {
        $tour = Tour::find($this->tourId);

        if ($tour === null) {
            return;
        }

        $route->execute($tour);
    }
}
