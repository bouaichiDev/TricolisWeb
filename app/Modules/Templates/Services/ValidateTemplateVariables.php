<?php

declare(strict_types=1);

namespace App\Modules\Templates\Services;

use Illuminate\Validation\ValidationException;

/**
 * Valide la structure d'`availableVariables`.
 *
 * Le §12 interdit d'inventer un schéma complexe. Celui retenu est le plus simple
 * qui réponde au besoin : une **liste plate de chemins**.
 *
 * ```json
 * ["order_number", "customer_name", "invoice.total", "invoice.lines"]
 * ```
 *
 * Le point sépare des segments **déclarés**, jamais une expression : la Phase 9
 * l'autorise parce qu'une facture expose des données structurées — `invoice`,
 * `customer`, `invoice.lines` — qu'un nom plat ne saurait nommer sans les
 * aplatir à la main dans chaque contexte de rendu.
 *
 * Ce que le motif continue d'exclure est ce qui compte : parenthèses, `$`,
 * espaces, `-`, `::`, `->`, séparateurs de chemin de fichier. Un chemin ne peut
 * donc désigner ni une méthode, ni un fichier. C'est la première barrière ; le
 * rendu en pose une seconde en refusant tout ce qui n'est pas déclaré, et une
 * troisième en n'exposant qu'un contexte construit depuis un DTO.
 */
final readonly class ValidateTemplateVariables
{
    private const string SEGMENT = '[a-zA-Z][a-zA-Z0-9_]{0,63}';

    /** Quatre segments au plus — la profondeur que le moteur sait résoudre. */
    public const string NAME_PATTERN = '/^'.self::SEGMENT.'(?:\.'.self::SEGMENT.'){0,3}$/';

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
                    'Chaque segment d’une variable doit commencer par une lettre et ne contenir que lettres, chiffres et tirets bas, avec quatre segments au plus.',
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
