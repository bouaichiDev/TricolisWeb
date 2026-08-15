<?php

declare(strict_types=1);

namespace App\Shared\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Motif de nom de fichier sûr (§21).
 *
 * Ce motif finit par nommer un fichier déposé sur un serveur distant : il ne
 * doit pas pouvoir remonter l'arborescence ni désigner un chemin absolu.
 *
 * **Aucun moteur de template n'est créé** : le §21 l'interdit, et le projet ne
 * définit aucun jeton. La règle ne fait que refuser ce qui est dangereux, sans
 * imposer de grammaire.
 */
final readonly class SafeFileNamePattern implements ValidationRule
{
    /** Caractères refusés par les systèmes de fichiers courants, plus les séparateurs. */
    private const string FORBIDDEN = '/[\/\\\\:*?"<>|\x00-\x1F]/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Le motif de nom de fichier doit être une chaîne.');

            return;
        }

        if (str_contains($value, '..')) {
            $fail('Le motif de nom de fichier ne peut pas contenir « .. ».');

            return;
        }

        if (preg_match(self::FORBIDDEN, $value) === 1) {
            $fail('Le motif de nom de fichier contient un caractère interdit.');
        }
    }
}
