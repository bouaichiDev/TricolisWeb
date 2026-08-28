<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Statuses\Services\StatusMachine;
use App\Shared\Database\MorphMap;

/**
 * Ce que « clôturée » veut dire pour une facture.
 *
 * **Le code vit ici, en un seul endroit.** Il est semé au référentiel — source
 * `invoice`, code `closed` — et le §19 interdit d'en faire une énumération PHP.
 * Le comparer à la main dans chaque garde donnerait autant d'occasions de se
 * tromper de casse.
 *
 * La transition elle-même reste gouvernée par `status_transitions` : c'est le
 * référentiel qui dit ce qu'une facture peut devenir, pas cette classe.
 */
final readonly class InvoiceClosure
{
    /** Code de la clôture, tel que semé au référentiel. */
    public const string CLOSED = 'closed';

    public function __construct(private StatusMachine $machine) {}

    public function isClosed(Invoice $invoice): bool
    {
        return $invoice->status === self::CLOSED;
    }

    /**
     * La facture peut-elle être clôturée en l'état ?
     *
     * Deux conditions, et la seconde n'est pas une formalité : le §8 impose
     * qu'une facture porte au moins une ligne, et clôturer une facture vide
     * enverrait au client un document sans prestation.
     */
    public function isClosable(Invoice $invoice): bool
    {
        if ($this->isClosed($invoice)) {
            return false;
        }

        return $this->machine->allows(MorphMap::INVOICE, $invoice->status, self::CLOSED)
            && $invoice->lines()->exists();
    }
}
