<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Services\Import\Parsers\PayloadParsers;
use App\Modules\Integrations\Services\Import\RecordExtractor;
use RuntimeException;

/**
 * Lit un contenu client et le rend sous forme de **lignes** (enregistrements).
 *
 * Une ligne est un tableau associatif : nom de colonne — ou structure
 * imbriquée — vers valeur. C'est la seule forme que l'interpréteur de
 * correspondance comprend, qu'elle vienne d'un CSV, d'un classeur Excel, d'un
 * JSON ou d'un XML.
 *
 * Les plafonds de `config/customer_integrations.php` s'appliquent ici, à
 * toutes les entrées : un fichier trop gros est refusé avant d'être analysé,
 * un lot trop long après.
 */
final readonly class ImportSourceReader
{
    public function __construct(
        private PayloadParsers $parsers,
        private RecordExtractor $records,
    ) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException contenu illisible, message destiné à l'utilisateur
     */
    public function read(string $contents, string $fileFormat, ?string $recordsPath = null): array
    {
        $maxBytes = (int) config('customer_integrations.files.max_bytes', 5 * 1024 * 1024);

        if (strlen($contents) > $maxBytes) {
            throw new RuntimeException(sprintf('Ce contenu dépasse la taille maximale de %d Ko.', intdiv($maxBytes, 1024)));
        }

        $parser = $this->parsers->for($fileFormat);
        $rows = $this->records->records($parser->parse($contents), $recordsPath, $parser->unwrapsRoot());
        $maxRows = (int) config('customer_integrations.files.max_rows', 5000);

        if (count($rows) > $maxRows) {
            throw new RuntimeException("Ce contenu porte plus de {$maxRows} enregistrements : découpez-le.");
        }

        return $rows;
    }
}
