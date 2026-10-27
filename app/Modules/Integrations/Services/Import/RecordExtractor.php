<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import;

use App\Modules\Integrations\Services\Mapping\SourcePath;
use RuntimeException;

/**
 * Tire d'un document les **enregistrements** que la correspondance lira.
 *
 * Un enregistrement est un tableau associatif. Selon le document :
 *
 * - `@records` déclaré : la liste (ou l'objet unique) à ce chemin ;
 * - une liste d'objets : chacun est un enregistrement ;
 * - un objet seul : c'est l'enregistrement — un fichier qui décrit une
 *   commande, et non un lot ;
 * - un XML (`unwrapRoot`) dont la racine ne porte qu'une collection
 *   (`<Orders><Order/><Order/></Orders>`) : cette collection.
 */
final readonly class RecordExtractor
{
    /**
     * @param  array<mixed>  $document
     * @return list<array<string, mixed>>
     */
    public function records(array $document, ?string $recordsPath, bool $unwrapRoot): array
    {
        if ($recordsPath !== null) {
            $found = SourcePath::collection($document, $recordsPath);

            if ($found === [] && ! $this->pathExists($document, $recordsPath)) {
                throw new RuntimeException("Le chemin des enregistrements « {$recordsPath} » est absent du document.");
            }

            return $found;
        }

        if ($document === []) {
            return [];
        }

        if (! array_is_list($document)) {
            return $unwrapRoot ? $this->unwrap($document) : [$document];
        }

        $records = [];

        foreach ($document as $index => $entry) {
            if (! is_array($entry) || $entry === [] || array_is_list($entry)) {
                throw new RuntimeException("L’élément {$index} de la liste n’est pas un objet.");
            }

            $records[] = $entry;
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $root
     * @return list<array<string, mixed>>
     */
    private function unwrap(array $root): array
    {
        $elements = array_filter(
            $root,
            static fn (int|string $key): bool => ! str_starts_with((string) $key, '@') && $key !== '#text',
            ARRAY_FILTER_USE_KEY,
        );

        if (count($elements) !== 1) {
            return [$root];
        }

        $only = reset($elements);

        if (is_array($only) && array_is_list($only)) {
            return array_values(array_filter($only, 'is_array'));
        }

        return is_array($only) ? [$only] : [$root];
    }

    /**
     * @param  array<mixed>  $document
     */
    private function pathExists(array $document, string $path): bool
    {
        $current = $document;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }
}
