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
 * Format : `ORD-2026-000001`, remis à zéro chaque année civile. La granularité
 * `scope` permet d'introduire plus tard une série par agence sans changer de
 * mécanisme.
 */
final readonly class GenerateOrderNumber
{
    private const string PREFIX = 'ORD';

    private const int PADDING = 6;

    public function execute(string $organizationId, int $year, string $scope = 'default'): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Le numéro de commande doit être attribué dans une transaction.');
        }

        $sequence = OrderNumberSequence::query()
            ->where('organization_id', $organizationId)
            ->where('scope', $scope)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            // firstOrCreate ne suffit pas : deux requêtes concurrentes peuvent
            // arriver ici en même temps. La contrainte unique arbitre, et le
            // perdant relit la ligne du gagnant sous verrou.
            $sequence = $this->createSequence($organizationId, $scope, $year);
        }

        $sequence->increment('last_number');

        return sprintf('%s-%d-%0'.self::PADDING.'d', self::PREFIX, $year, $sequence->last_number);
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
