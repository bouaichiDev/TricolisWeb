<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import\Parsers;

use JsonException;
use RuntimeException;

/**
 * Un JSON : un objet, une liste d'objets, ou une enveloppe (`{"data": […]}`)
 * dont `@records` désigne la liste.
 */
final readonly class JsonPayloadParser implements PayloadParser
{
    /** Profondeur de décodage : un JSON de commande n'en demande pas davantage. */
    private const int DEPTH = 64;

    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        try {
            $decoded = json_decode($contents, true, self::DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Ce contenu n’est pas un JSON valide : '.$exception->getMessage());
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Un fichier JSON doit contenir un objet ou une liste d’objets.');
        }

        return $decoded;
    }

    public function unwrapsRoot(): bool
    {
        return false;
    }
}
