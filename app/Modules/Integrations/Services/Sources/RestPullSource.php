<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Integrations\Services\Import\RecordExtractor;
use App\Modules\Integrations\Services\Mapping\SourcePath;
use DateTimeInterface;
use RuntimeException;

/**
 * Relève les commandes exposées par l'API d'un client (pull REST JSON).
 *
 * Page après page, selon la pagination décrite par la configuration, jusqu'à
 * une page vide, une page incomplète, un curseur absent ou le plafond de
 * pages. Le filtre « depuis » reprend la date du dernier pull réussi
 * (`last_used_at`), s'il est configuré.
 */
final readonly class RestPullSource
{
    public function __construct(
        private InboundHttpClient $http,
        private RecordExtractor $records,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function fetch(OrganizationApiConfiguration $api, ?string $mappingRecordsPath, ?DateTimeInterface $since): array
    {
        $settings = RestPullSettings::from($api->settings);
        $recordsPath = $settings->recordsPath ?? $mappingRecordsPath;
        $query = $settings->query;

        if ($settings->sinceParam !== null && $since !== null) {
            $query[$settings->sinceParam] = $since->format($settings->sinceFormat);
        }

        $all = [];
        $position = $settings->start;
        $cursor = null;

        for ($page = 0; $page < $settings->maxPages; $page++) {
            $pageQuery = $this->pageQuery($settings, $query, $position, $cursor);
            $document = $this->http->getJson($api, $settings, $settings->path, $pageQuery);
            $batch = $this->extract($document, $recordsPath);
            $all = [...$all, ...$batch];

            if ($settings->paginationType === 'none' || $batch === []) {
                break;
            }

            if ($settings->paginationType === 'cursor') {
                $cursor = $settings->cursorPath === null ? null : SourcePath::value($document, $settings->cursorPath);

                if (! is_scalar($cursor) || $cursor === '') {
                    break;
                }

                continue;
            }

            if (count($batch) < $settings->pageSize) {
                break;
            }

            $position += $settings->paginationType === 'offset' ? count($batch) : 1;
        }

        return $all;
    }

    /**
     * Un appel de santé, sans rien importer — pour « Tester la connexion ».
     *
     * @return array{httpStatus: int, path: string}
     */
    public function probe(OrganizationApiConfiguration $api): array
    {
        $settings = RestPullSettings::from($api->settings);
        $path = $settings->healthPath ?? $settings->path;
        $response = $this->http->get($api, $settings, $path, $settings->healthPath === null ? $this->pageQuery($settings, $settings->query, $settings->start, null) : []);

        return ['httpStatus' => $response->status(), 'path' => $path];
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, scalar>
     */
    private function pageQuery(RestPullSettings $settings, array $query, int $position, mixed $cursor): array
    {
        if ($settings->paginationType === 'none') {
            return $query;
        }

        if ($settings->sizeParam !== null) {
            $query[$settings->sizeParam] = $settings->pageSize;
        }

        if ($settings->paginationType === 'cursor') {
            if (is_scalar($cursor) && $cursor !== '') {
                $query[$settings->pageParam] = $cursor;
            }

            return $query;
        }

        $query[$settings->pageParam] = $position;

        return $query;
    }

    /**
     * @param  array<mixed>  $document
     * @return list<array<string, mixed>>
     */
    private function extract(array $document, ?string $recordsPath): array
    {
        try {
            return $this->records->records($document, $recordsPath, false);
        } catch (RuntimeException $exception) {
            throw new PermanentIntegrationFailure($exception->getMessage());
        }
    }
}
