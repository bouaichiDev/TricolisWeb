<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Une création concurrente peut passer la validation puis heurter l'index
 * unique (code, e-mail, immatriculation). Le portail répond alors le même
 * refus neutre que la validation — jamais une erreur 500, jamais le nom de
 * l'index ni la valeur en conflit.
 */
final class NeutralDuplicate
{
    /**
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    public static function guard(callable $write, string $field, string $message): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }
}
