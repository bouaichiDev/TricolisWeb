<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Mapping;

use DateTimeImmutable;

/**
 * Conversions contrôlées d'une valeur source.
 *
 * **Une conversion impossible rend la valeur d'origine**, jamais `null` : la
 * validation dira alors « la quantité doit être un nombre » au lieu de laisser
 * croire que la colonne était vide.
 *
 * Les décimaux restent des chaînes : une quantité ne transite jamais par un
 * flottant, qui la rendrait inexacte.
 */
final class ValueCaster
{
    /** Formats de date acceptés, du plus courant au moins courant. */
    private const array DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'd.m.Y', 'd-m-Y', 'Y/m/d', 'Ymd'];

    private const array DATETIME_FORMATS = ['Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'd/m/Y H:i', 'd/m/Y H:i:s'];

    public static function cast(mixed $value, string $type): mixed
    {
        if ($value === null || is_array($value)) {
            return $value;
        }

        return match ($type) {
            'string' => is_scalar($value) ? (string) $value : $value,
            'integer' => self::integer($value),
            'decimal' => self::decimal($value),
            'boolean' => self::boolean($value),
            'date' => self::temporal($value, self::DATE_FORMATS, 'Y-m-d'),
            'datetime' => self::temporal($value, self::DATETIME_FORMATS, 'Y-m-d H:i:s'),
            'time' => self::temporal($value, ['H:i', 'H:i:s', 'G:i', 'Hi'], 'H:i'),
            default => $value,
        };
    }

    private static function integer(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        $text = str_replace([' ', "\u{00A0}"], '', (string) $value);

        return preg_match('/^-?\d+$/', $text) === 1 ? (int) $text : $value;
    }

    private static function decimal(mixed $value): mixed
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        // « 1 250,5 » comme « 1250.5 » : l'espace sépare les milliers, la
        // virgule est décimale quand il n'y a pas de point.
        $text = str_replace([' ', "\u{00A0}"], '', (string) $value);

        if (! str_contains($text, '.')) {
            $text = str_replace(',', '.', $text);
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $text) === 1 ? $text : $value;
    }

    private static function boolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        return match (strtolower(trim((string) $value))) {
            '1', 'true', 'yes', 'y', 'oui', 'o', 'x' => true,
            '0', 'false', 'no', 'n', 'non' => false,
            default => $value,
        };
    }

    /**
     * @param  list<string>  $formats
     */
    private static function temporal(mixed $value, array $formats, string $output): mixed
    {
        $text = trim((string) $value);

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$format, $text);
            $errors = DateTimeImmutable::getLastErrors();

            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed->format($output);
            }
        }

        return $value;
    }
}
