<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Models\OrderNumberSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Attribue un numéro de commande unique, sans collision en concurrence.
 *
 * La valeur n'est jamais dérivée d'un `count()` ni d'un `max()` : deux créations
 * simultanées liraient la même valeur et produiraient le même numéro. La ligne
 * de compteur est verrouillée en base (`SELECT … FOR UPDATE`) pour la durée de
 * la transaction, ce qui sérialise les créations concurrentes d'une même série
 * et laisse passer les autres.
 *
 * Format : `CH-01-2026-000001` — **le code du client**, l'année, le rang.
 *
 * Le préfixe fixe `ORD` ne disait rien : il fallait ouvrir la commande pour
 * savoir de qui elle venait. Le code client répond dès la liste, et c'est le
 * repère que donne le client lui-même quand il rappelle.
 *
 * **Une série par client**, portée par le `scope` du compteur — le mécanisme
 * était prévu pour ça. Chaque client numérote donc à partir de un, remis à zéro
 * chaque année civile. Un compteur commun donnerait des rangs qui sautent, et
 * un client ne comprendrait pas de passer de sa commande 12 à sa commande 431.
 */
final readonly class GenerateOrderNumber
{
    private const int PADDING = 6;

    /**
     * @param  string  $series  Le code du client : préfixe du numéro, et clé de
     *                          son compteur. Deux clients ne se partagent ni
     *                          l'un ni l'autre.
     */
    public function execute(string $organizationId, int $year, string $series): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Le numéro de commande doit être attribué dans une transaction.');
        }

        $sequence = OrderNumberSequence::query()
            ->where('organization_id', $organizationId)
            ->where('scope', $series)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            // firstOrCreate ne suffit pas : deux requêtes concurrentes peuvent
            // arriver ici en même temps. La contrainte unique arbitre, et le
            // perdant relit la ligne du gagnant sous verrou.
            $sequence = $this->createSequence($organizationId, $series, $year);
        }

        $sequence->increment('last_number');

        return sprintf('%s-%d-%0'.self::PADDING.'d', $series, $year, $sequence->last_number);
    }

    private function createSequence(string $organizationId, string $scope, int $year): OrderNumberSequence
    {
        try {
            return OrderNumberSequence::create([
                'organization_id' => $organizationId,
                'scope' => $scope,
                'year' => $year,
                'last_number' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            return OrderNumberSequence::query()
                ->where('organization_id', $organizationId)
                ->where('scope', $scope)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }
}
