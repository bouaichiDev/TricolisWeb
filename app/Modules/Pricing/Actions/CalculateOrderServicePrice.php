<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Pricing\DTOs\PriceOutcome;
use App\Modules\Pricing\Exceptions\InvalidFormula;
use App\Modules\Pricing\Models\PricingCalculation;
use App\Modules\Pricing\Services\FormulaEvaluator;
use App\Modules\Pricing\Services\FormulaParser;
use App\Modules\Pricing\Services\PricingContext;
use App\Modules\Pricing\Services\PricingResolver;

/**
 * Calcule le prix client d'une prestation.
 *
 * Le chemin est celui du §169BN : contexte, résolution, évaluation, historique.
 * Chaque étape est un service à part — le §169BM refuse un service géant, et
 * ces quatre-là se testent séparément.
 *
 * **L'historique n'est écrit que pour un calcul retenu.** Un aperçu ne doit
 * rien laisser : le §169AH interdit d'enregistrer le résultat d'une
 * prévisualisation comme prix définitif, et une table d'historique remplie
 * d'essais n'expliquerait plus rien.
 *
 * **Aucun repli silencieux sur l'ancien prix** (§169BO) : si le calcul échoue,
 * l'issue le dit. Reprendre `customer_unit_price` ferait passer un prix périmé
 * pour un prix calculé.
 */
final readonly class CalculateOrderServicePrice
{
    public function __construct(
        private PricingContext $context,
        private PricingResolver $resolver,
        private FormulaParser $parser,
        private FormulaEvaluator $evaluator,
    ) {}

    public function execute(OrderService $service, string $organizationId, bool $record = true): PriceOutcome
    {
        $service->loadMissing(['order', 'service', 'address']);

        $customerId = $service->order?->customer_id;

        if ($customerId === null) {
            return PriceOutcome::notConfigured();
        }

        $context = $this->context->build($service);

        $pricing = $this->resolver->resolve(
            $organizationId,
            $customerId,
            $service->service_id,
            $context,
            $service->requested_date?->toDateString(),
        );

        if ($pricing === null) {
            return PriceOutcome::notConfigured();
        }

        $variables = $this->context->numeric($context);

        try {
            $amount = $this->evaluator->evaluate($this->parser->parse($pricing->rule->formula), $variables);
        } catch (InvalidFormula $exception) {
            return PriceOutcome::failed($exception->getMessage(), $pricing, $variables);
        }

        $outcome = PriceOutcome::priced($amount, $pricing, $variables);

        if ($record) {
            $this->record($service, $organizationId, $customerId, $outcome);
        }

        return $outcome;
    }

    private function record(
        OrderService $service,
        string $organizationId,
        string $customerId,
        PriceOutcome $outcome,
    ): void {
        PricingCalculation::create([
            'organization_id' => $organizationId,
            'order_service_id' => $service->id,
            'customer_id' => $customerId,
            'price_list_id' => $outcome->pricing?->priceList->id,
            'price_rule_id' => $outcome->pricing?->rule->id,
            'price_matrix_id' => $outcome->pricing?->matrix?->id,
            'price_matrix_row_id' => $outcome->pricing?->row?->id,
            'scope' => $outcome->pricing?->scope() ?? '',
            'service_code' => $service->service?->code,
            // La formule est recopiee : si elle change demain, la facture
            // d'hier doit continuer a s'expliquer par celle qui l'a produite.
            'formula_snapshot' => $outcome->pricing?->rule->formula ?? '',
            'variables_snapshot' => $outcome->variables,
            'result' => $outcome->amount,
            'currency_code' => $service->order?->currency_code ?? 'MAD',
            'calculated_at' => now(),
        ]);
    }
}
