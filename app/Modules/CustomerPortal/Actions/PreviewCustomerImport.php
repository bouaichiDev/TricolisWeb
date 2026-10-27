<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Exceptions\CustomerImportRefused;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\Integrations\CustomerImportRuns;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Import\ImportPipeline;
use App\Modules\Integrations\Services\ImportSourceReader;
use App\Modules\Integrations\Services\Mapping\MappingDirectives;
use App\Modules\Integrations\Services\Mapping\MappingPairs;
use App\Modules\Integrations\Services\Uploads\ImportUploadStore;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Prévisualiser un fichier déposé dans le portail — **sans rien créer**.
 *
 * Le fichier est lu, la correspondance appliquée, chaque commande jugée comme
 * l'import la jugerait (règles de commande, règles de la configuration,
 * doublon). Le fichier est gardé sur le disque privé derrière un jeton à
 * durée courte : la confirmation relira **ce** fichier, pas un autre.
 */
final readonly class PreviewCustomerImport
{
    private const int SAMPLE_ROWS = 5;

    public function __construct(
        private CurrentCustomerContext $context,
        private ImportSourceReader $reader,
        private ImportPipeline $pipeline,
        private ImportUploadStore $uploads,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws CustomerImportRefused
     */
    public function execute(CustomerImportConfiguration $configuration, UploadedFile $file): array
    {
        $refusal = CustomerImportRuns::refusal($configuration);

        if ($refusal !== null) {
            throw new CustomerImportRefused($refusal);
        }

        try {
            $rows = $this->reader->read(
                (string) $file->get(),
                (string) $configuration->file_format,
                MappingDirectives::recordsPath($configuration->mapping),
            );
        } catch (RuntimeException $exception) {
            throw new CustomerImportRefused($exception->getMessage());
        }

        if ($rows === []) {
            throw new CustomerImportRefused('Ce fichier ne contient aucune ligne.');
        }

        $upload = $this->uploads->put($file, $this->context->customerId(), $configuration->id, $this->context->user()->id);

        return [
            ...$upload,
            'configuration' => ['id' => $configuration->id, 'name' => $configuration->name],
            'rowCount' => count($rows),
            'headers' => array_keys($rows[0]),
            'sampleRows' => array_map($this->flatten(...), array_slice($rows, 0, self::SAMPLE_ROWS)),
            'mapping' => MappingPairs::of($configuration->mapping),
            'summary' => $this->pipeline->preview($configuration, $rows),
        ];
    }

    /**
     * Une ligne d'échantillon, valeurs simples seulement : une structure
     * imbriquée s'affiche comme telle, pas en entier.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, scalar|null>
     */
    private function flatten(array $row): array
    {
        return array_map(
            static fn (mixed $value): mixed => is_array($value) ? '[…]' : $value,
            $row,
        );
    }
}
