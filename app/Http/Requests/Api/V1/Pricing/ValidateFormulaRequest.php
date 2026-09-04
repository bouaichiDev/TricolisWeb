<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pricing;

use App\Modules\Pricing\Services\FormulaTokenizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Vérifier une formule, et l'essayer sur des valeurs.
 *
 * La longueur est bornée ici comme dans le moteur : refuser tôt évite de faire
 * lire au parseur ce qu'il rejettera de toute façon.
 */
class ValidateFormulaRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'formula' => ['required', 'string', 'max:'.FormulaTokenizer::MAX_LENGTH],
            // Les valeurs d'essai : facultatives, la validation seule n'en a
            // pas besoin.
            'variables' => ['sometimes', 'array'],
            'variables.*' => ['nullable', 'numeric'],
        ];
    }
}
