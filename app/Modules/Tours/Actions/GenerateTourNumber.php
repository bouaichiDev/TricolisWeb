<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourNumberSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Attribue un numéro de tournée, sans collision en concurrence.
 *
 * **Un entier qui avance de un**, tel que demandé le 27 août 2026 : pas de
 * préfixe ni d'année, contrairement aux commandes. Le numéro n'est plus saisi —
 * un opérateur qui le tape produit des doublons et des trous, et la contrainte
 * d'unicité ne le lui apprend qu'au moment d'enregistrer.
 *
 * La valeur n'est jamais dérivée d'un `count()` ni d'un `max()` : deux créations
 * simultanées liraient la même et produiraient le même numéro. La ligne de
 * compteur est verrouillée en base pour la durée de la transaction.
 *
 * **Les numéros déjà en base sont contournés, pas écrasés.** Une organisation
 * qui a saisi « 9988799 » à la main garde ce numéro ; la suite repart de 1 et
 * saute ce qui est pris. Repartir au-dessus du plus grand donnerait des numéros
 * à sept chiffres pour toujours, à cause d'une frappe d'essai.
 */
final readonly class GenerateTourNumber
{
    /**
     * Garde-fou : au-delà, c'est que quelque chose d'autre ne va pas.
     *
     * Sans borne, une organisation dont tous les numéros seraient pris ferait
     * tourner la boucle indéfiniment.
     */
    private const int MAX_ATTEMPTS = 1000;

    public function execute(string $organizationId): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Le numéro de tournée doit être attribué dans une transaction.');
        }

        $sequence = TourNumberSequence::where('organization_id', $organizationId)
            ->lockForUpdate()
            ->first() ?? $this->createSequence($organizationId);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $sequence->increment('last_number');
            $candidate = (string) $sequence->last_number;

            if (! $this->taken($organizationId, $candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Aucun numéro de tournée disponible après '.self::MAX_ATTEMPTS.' essais.');
    }

    private function taken(string $organizationId, string $number): bool
    {
        return Tour::where('organization_id', $organizationId)
            ->where('tour_number', $number)
            ->exists();
    }

    private function createSequence(string $organizationId): TourNumberSequence
    {
        try {
            return TourNumberSequence::create([
                'organization_id' => $organizationId,
                'last_number' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Deux requetes ont pu arriver ici ensemble : la contrainte arbitre,
            // et le perdant relit la ligne du gagnant sous verrou.
            return TourNumberSequence::where('organization_id', $organizationId)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }
}
