<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import\Parsers;

use RuntimeException;

/**
 * Transforme un contenu brut — fichier ou réponse d'API — en document PHP.
 *
 * Un document est un tableau : une liste de lignes pour un CSV ou un classeur,
 * une structure imbriquée pour un JSON ou un XML. `RecordExtractor` en tire
 * ensuite les enregistrements.
 */
interface PayloadParser
{
    /**
     * @return array<mixed>
     *
     * @throws RuntimeException contenu illisible, message destiné à l'utilisateur
     */
    public function parse(string $contents): array;

    /**
     * Un document de ce format peut-il porter ses enregistrements sous une
     * racine unique (`<Orders><Order/>…</Orders>`) qu'il faut dérouler ?
     */
    public function unwrapsRoot(): bool;
}
