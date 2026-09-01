<?php

declare(strict_types=1);

namespace App\Shared\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Une liste de destinataires, saisie en liste ou séparée par des virgules.
 *
 * Les deux formes existent parce que les deux se saisissent naturellement : un
 * champ texte donne une chaîne, une API donne un tableau. Refuser l'une
 * obligerait l'appelant à deviner laquelle on attend.
 *
 * **Chaque adresse est vérifiée, et une seule invalide fait échouer.** Une
 * facture envoyée à trois destinataires sur quatre passerait pour un succès
 * alors qu'un client ne l'a jamais reçue.
 */
final readonly class EmailRecipients implements ValidationRule
{
    private const int MAX = 20;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $values = is_string($value) ? explode(',', $value) : $value;

        if (! is_array($values)) {
            $fail('Les destinataires doivent être une adresse ou une liste d’adresses.');

            return;
        }

        $addresses = array_values(array_filter(array_map(
            static fn ($entry): string => is_string($entry) ? trim($entry) : '',
            $values,
        ), static fn (string $entry): bool => $entry !== ''));

        if ($addresses === []) {
            $fail('Au moins un destinataire est attendu.');

            return;
        }

        if (count($addresses) > self::MAX) {
            $fail(sprintf('Pas plus de %d destinataires.', self::MAX));

            return;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                $fail(sprintf('« %s » n’est pas une adresse électronique valide.', $address));

                return;
            }
        }
    }
}
