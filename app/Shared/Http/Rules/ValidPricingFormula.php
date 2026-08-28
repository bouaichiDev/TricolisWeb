<?php

declare(strict_types=1);

namespace App\Shared\Http\Rules;

use App\Modules\Pricing\Exceptions\InvalidFormula;
use App\Modules\Pricing\Services\FormulaParser;
use App\Modules\Pricing\Services\PricingContext;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuse une formule que le moteur ne saurait pas calculer.
 *
 * **Le même parseur que le calcul réel.** Valider avec une autre grammaire
 * laisserait passer des formules acceptées à l'enregistrement et fautives à la
 * facturation — l'erreur se découvrirait alors devant un client.
 *
 * Un paramètre hors de la liste blanche est refusé ici plutôt qu'à l'exécution :
 * `{P:temperature}` n'a aucune chance d'aboutir, et le dire tout de suite évite
 * un barème qui ne calcule jamais.
 */
final readonly class ValidPricingFormula implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('La formule doit être du texte.');

            return;
        }

        $parser = Container::getInstance()->make(FormulaParser::class);

        try {
            $node = $parser->parse($value);
        } catch (InvalidFormula $exception) {
            $fail($exception->getMessage());

            return;
        }

        $unknown = array_diff($parser->variables($node), PricingContext::VARIABLES);

        if ($unknown !== []) {
            $fail(sprintf(
                'Paramètre inconnu : %s. Disponibles : %s.',
                implode(', ', $unknown),
                implode(', ', PricingContext::VARIABLES),
            ));
        }
    }
}
