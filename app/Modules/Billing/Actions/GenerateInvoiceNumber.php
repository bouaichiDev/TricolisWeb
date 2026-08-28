<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceNumberSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Attribue un numéro de facture, sans collision en concurrence.
 *
 * **Il n'est plus saisi quand on ne le saisit pas.** Un facturier qui tape son
 * numéro produit des doublons et des trous, et l'unicité ne le lui apprend
 * qu'au moment d'enregistrer — après avoir composé la facture entière. Le
 * numéro fourni reste accepté : une organisation qui reprend une série existante
 * doit pouvoir la continuer.
 *
 * La valeur n'est jamais dérivée d'un `count()` ni d'un `max()` : deux créations
 * simultanées liraient la même et produiraient le même numéro. La ligne de
 * compteur est verrouillée pour la durée de la transaction.
 *
 * **Les numéros déjà pris sont contournés, pas écrasés.** Une facture saisie à
 * la main garde le sien ; la suite avance et saute ce qui existe. Repartir
 * au-dessus du plus grand donnerait des numéros à sept chiffres pour toujours,
 * à cause d'une frappe d'essai.
 *
 * Format `INV-2026-000001`, remis à zéro chaque année civile — comme les
 * commandes, parce qu'une facture se classe par exercice.
 */
final readonly class GenerateInvoiceNumber
{
    private const string PREFIX = 'INV';

    private const int PADDING = 6;

    /** Garde-fou : au-delà, c'est qu'autre chose ne va pas. */
    private const int MAX_ATTEMPTS = 1000;

    public function execute(string $organizationId, int $year, string $scope = 'default'): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Le numéro de facture doit être attribué dans une transaction.');
        }

        $sequence = InvoiceNumberSequence::query()
            ->where('organization_id', $organizationId)
            ->where('scope', $scope)
            ->where('year', $year)
            ->lockForUpdate()
            ->first() ?? $this->createSequence($organizationId, $scope, $year);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $sequence->increment('last_number');

            $candidate = sprintf('%s-%d-%0'.self::PADDING.'d', self::PREFIX, $year, $sequence->last_number);

            if (! $this->taken($organizationId, $candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Aucun numéro de facture disponible après '.self::MAX_ATTEMPTS.' essais.');
    }

    private function taken(string $organizationId, string $number): bool
    {
        return Invoice::where('organization_id', $organizationId)
            ->where('invoice_number', $number)
            ->exists();
    }

    private function createSequence(string $organizationId, string $scope, int $year): InvoiceNumberSequence
    {
        try {
            return InvoiceNumberSequence::create([
                'organization_id' => $organizationId,
                'scope' => $scope,
                'year' => $year,
                'last_number' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Deux requetes ont pu arriver ici ensemble : la contrainte arbitre,
            // et le perdant relit la ligne du gagnant sous verrou.
            return InvoiceNumberSequence::query()
                ->where('organization_id', $organizationId)
                ->where('scope', $scope)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }
}
