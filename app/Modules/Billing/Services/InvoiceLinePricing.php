<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Pricing\Actions\CalculateOrderServicePrice;
use Illuminate\Validation\ValidationException;

/**
 * Le prix d'une ligne de facture adossée à une prestation.
 *
 * **Le barème décide, pas l'écran** (§169AK). Le montant envoyé par React n'est
 * pas repris quand un barème s'applique : deux calculs finiraient par diverger,
 * et c'est la facture qui aurait tort.
 *
 * **Sans barème, on ne facture pas au hasard.** Le §169AJ interdit de produire
 * une ligne à zéro faute de tarif, et le §169BO interdit de reprendre
 * *silencieusement* un ancien prix. La ligne est donc refusée, avec le nom de
 * la prestation et la raison.
 *
 * **Sauf si le prix est assumé.** `priceOverride` dit que le montant soumis est
 * une décision, pas un défaut : c'est la « règle explicite » que le §169BO
 * autorise, et le §169BP demande justement de distinguer le prix calculé du
 * prix facturé. Le drapeau ne contourne rien quand un barème existe — là, le
 * calcul l'emporte toujours.
 *
 * Deux chemins visibles, donc, et jamais un repli muet :
 *
 * - un barème s'applique : son prix vaut, et le calcul est historisé ;
 * - aucun barème : soit on en écrit un, soit on assume un prix.
 */
final readonly class InvoiceLinePricing
{
    public function __construct(private CalculateOrderServicePrice $calculate) {}

    /**
     * Le prix unitaire à porter sur la ligne.
     *
     * @param  string  $submitted  le montant soumis, retenu seulement s'il est
     *                             assumé et qu'aucun barème ne s'applique
     */
    public function unitPrice(
        OrderService $service,
        string $organizationId,
        string $submitted,
        bool $priceOverride,
        string $field,
    ): string {
        $outcome = $this->calculate->execute($service, $organizationId);

        if ($outcome->priced) {
            return (string) $outcome->amount;
        }

        if ($priceOverride) {
            // Aucun PricingCalculation n'est ecrit : il n'y a pas eu de calcul,
            // et l'historique ne doit pas laisser croire le contraire.
            return $submitted;
        }

        throw ValidationException::withMessages([
            $field => sprintf(
                '%s pour la prestation %s. Configurez un barème, ou assumez le prix saisi.',
                $outcome->reason ?? 'Tarif non configuré',
                $service->service_number,
            ),
        ]);
    }
}
