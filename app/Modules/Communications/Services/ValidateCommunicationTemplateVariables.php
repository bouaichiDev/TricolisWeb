<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

use Illuminate\Validation\ValidationException;

/**
 * Valide la structure d'`availableVariables`.
 *
 * Le §12 interdit d'inventer un schéma complexe. Celui retenu est le plus simple
 * qui réponde au besoin : une **liste plate de noms**.
 *
 * ```json
 * ["order_number", "customer_name", "delivery_date"]
 * ```
 *
 * Le motif de nom exclut mécaniquement les points, parenthèses, `$`, espaces et
 * chemins : un nom de variable ne peut donc jamais désigner une méthode, un
 * fichier ou une propriété chaînée. C'est la première barrière ; le rendu en
 * pose une seconde en refusant tout ce qui n'est pas déclaré.
 */
final readonly class ValidateCommunicationTemplateVariables
{
    public const string NAME_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/';

    private const int MAX_VARIABLES = 100;

    /**
     * @param  mixed  $variables  valeur brute reçue
     * @return list<string>|null
     *
     * @throws ValidationException
     */
    public function validate(mixed $variables, string $field = 'availableVariables'): ?array
    {
        if ($variables === null) {
            return null;
        }

        if (! is_array($variables) || ! array_is_list($variables)) {
            $this->fail($field, 'Les variables disponibles doivent être une liste de noms.');
        }

        if (count($variables) > self::MAX_VARIABLES) {
            $this->fail($field, 'Une liste de variables ne peut pas dépasser '.self::MAX_VARIABLES.' entrées.');
        }

        $names = [];

        foreach ($variables as $index => $name) {
            if (! is_string($name) || preg_match(self::NAME_PATTERN, $name) !== 1) {
                $this->fail(
                    "{$field}.{$index}",
                    'Un nom de variable doit commencer par une lettre et ne contenir que lettres, chiffres et tirets bas.',
                );
            }

            if (in_array($name, $names, true)) {
                $this->fail("{$field}.{$index}", "La variable « {$name} » est déclarée deux fois.");
            }

            $names[] = $name;
        }

        return $names;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
