<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pricing\ValidateFormulaRequest;
use App\Modules\Pricing\Exceptions\InvalidFormula;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Services\FormulaEvaluator;
use App\Modules\Pricing\Services\FormulaParser;
use App\Modules\Pricing\Services\PricingContext;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Vérifier et essayer une formule tarifaire.
 *
 * **Le même moteur que le calcul réel.** Le §169AF l'exige : un second
 * évaluateur écrit en JavaScript finirait par diverger, et l'écran annoncerait
 * un prix que la facture ne confirmerait pas.
 *
 * Cette route **n'exécute rien** (§169AG) : elle lit la formule, en construit
 * l'arbre, et l'évalue sur des nombres. Il n'y a pas de chemin par lequel du
 * code arriverait jusqu'à PHP.
 */
class FormulaController extends Controller
{
    public function __construct(
        private readonly FormulaParser $parser,
        private readonly FormulaEvaluator $evaluator,
        private readonly PricingContext $context,
    ) {}

    /**
     * Valider une formule, et l'évaluer si des valeurs sont fournies.
     *
     * Permission requise : `price_lists.view` — lire un barème et vérifier une
     * formule relèvent du même geste.
     */
    public function validateFormula(ValidateFormulaRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [PriceList::class, $organizationId]);

        $formula = (string) $request->validated('formula');

        try {
            $node = $this->parser->parse($formula);
        } catch (InvalidFormula $exception) {
            return ApiResponse::ok([
                'valid' => false,
                'error' => $exception->getMessage(),
                'variables' => [],
                'unknownVariables' => [],
                'result' => null,
            ]);
        }

        $used = $this->parser->variables($node);
        $unknown = array_values(array_diff($used, $this->context->numericNames()));

        return ApiResponse::ok([
            'valid' => $unknown === [],
            'error' => $unknown === [] ? null : $this->unknownMessage($unknown),
            'variables' => $used,
            'unknownVariables' => $unknown,
            'result' => $unknown === [] ? $this->tryEvaluate($node, $request) : null,
        ]);
    }

    /**
     * Le résultat de l'essai, ou son échec.
     *
     * Une valeur manquante n'est pas une erreur de formule : on n'a simplement
     * rien à calculer tant qu'elle n'est pas saisie.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function tryEvaluate(array $node, ValidateFormulaRequest $request): ?array
    {
        /** @var array<string, string|null> $variables */
        $variables = $request->validated('variables') ?? [];

        if ($variables === []) {
            return null;
        }

        try {
            return ['amount' => $this->evaluator->evaluate($node, $variables), 'error' => null];
        } catch (InvalidFormula $exception) {
            return ['amount' => null, 'error' => $exception->getMessage()];
        }
    }

    /**
     * @param  list<string>  $unknown
     */
    private function unknownMessage(array $unknown): string
    {
        return sprintf(
            'Paramètre inconnu : %s. Disponibles : %s.',
            implode(', ', $unknown),
            implode(', ', $this->context->numericNames()),
        );
    }
}
