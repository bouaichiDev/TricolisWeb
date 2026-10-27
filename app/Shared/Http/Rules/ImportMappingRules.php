<?php

declare(strict_types=1);

namespace App\Shared\Http\Rules;

use App\Modules\Integrations\Services\Mapping\MappingSyntax;
use App\Modules\Integrations\Services\Validation\ImportValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuse une correspondance ou des règles de validation que le moteur
 * d'import ne saurait pas appliquer.
 *
 * `mapping` : directives `@…` bien formées (types de la liste, pas de
 * `@source` et `@value` ensemble). `validationRules` : règles choisies dans
 * `ImportValidationRules::ALLOWED`, options connues.
 *
 * Le type « tableau » reste contrôlé par la règle `array` voisine : ceci ne
 * juge que le contenu.
 */
final readonly class ImportMappingRules implements ValidationRule
{
    public const string MAPPING = 'mapping';

    public const string VALIDATION = 'validation';

    public function __construct(private string $kind) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $problems = $this->kind === self::MAPPING
            ? MappingSyntax::problems($value)
            : ImportValidationRules::problems($value);

        foreach (array_slice($problems, 0, 10) as $problem) {
            $fail($problem);
        }
    }
}
