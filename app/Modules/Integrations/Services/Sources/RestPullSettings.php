<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

/**
 * Les réglages d'un pull REST, lus dans `OrganizationApiConfiguration.settings`.
 *
 * Aucun protocole de fournisseur n'est codé ici : la pagination et le filtre
 * « depuis » sont **décrits** par la configuration, avec les seuls modes
 * génériques que les API rencontrées emploient.
 *
 * ```json
 * {
 *   "path": "/orders", "recordsPath": "data", "query": {"status": "open"},
 *   "since": {"param": "updated_since", "format": "Y-m-d\\TH:i:sP"},
 *   "pagination": {"type": "page", "param": "page", "sizeParam": "per_page",
 *                  "size": 100, "start": 1, "maxPages": 20},
 *   "healthPath": "/health", "actorUserId": "01J…", "apiKeyHeader": "X-Api-Key"
 * }
 * ```
 *
 * Pagination : `none` · `page` · `offset` · `cursor` (`cursorPath` dans la
 * réponse, envoyé dans `param`). Un réglage absent vaut « rien de plus ».
 */
final readonly class RestPullSettings
{
    /** @var list<string> */
    public const array PAGINATION_TYPES = ['none', 'page', 'offset', 'cursor'];

    /**
     * @param  array<string, scalar>  $query
     */
    public function __construct(
        public string $path,
        public ?string $recordsPath,
        public array $query,
        public ?string $sinceParam,
        public string $sinceFormat,
        public string $paginationType,
        public string $pageParam,
        public ?string $sizeParam,
        public int $pageSize,
        public int $start,
        public ?string $cursorPath,
        public int $maxPages,
        public ?string $healthPath,
        public ?string $actorUserId,
        public string $apiKeyHeader,
    ) {}

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public static function from(?array $settings): self
    {
        $settings ??= [];
        $pagination = is_array($settings['pagination'] ?? null) ? $settings['pagination'] : [];
        $since = is_array($settings['since'] ?? null) ? $settings['since'] : [];
        $type = $pagination['type'] ?? 'none';
        $ceiling = (int) config('customer_integrations.pull.max_pages', 50);

        return new self(
            path: self::text($settings['path'] ?? null) ?? '',
            recordsPath: self::text($settings['recordsPath'] ?? null),
            query: array_filter(
                is_array($settings['query'] ?? null) ? $settings['query'] : [],
                static fn (mixed $value, int|string $key): bool => is_string($key) && is_scalar($value),
                ARRAY_FILTER_USE_BOTH,
            ),
            sinceParam: self::text($since['param'] ?? null),
            sinceFormat: self::text($since['format'] ?? null) ?? DATE_ATOM,
            paginationType: in_array($type, self::PAGINATION_TYPES, true) ? $type : 'none',
            pageParam: self::text($pagination['param'] ?? null) ?? ($type === 'offset' ? 'offset' : ($type === 'cursor' ? 'cursor' : 'page')),
            sizeParam: self::text($pagination['sizeParam'] ?? null),
            pageSize: max(1, min(1000, (int) ($pagination['size'] ?? 100))),
            start: max(0, (int) ($pagination['start'] ?? ($type === 'offset' ? 0 : 1))),
            cursorPath: self::text($pagination['cursorPath'] ?? null),
            maxPages: max(1, min($ceiling, (int) ($pagination['maxPages'] ?? $ceiling))),
            healthPath: self::text($settings['healthPath'] ?? null),
            actorUserId: self::text($settings['actorUserId'] ?? null),
            apiKeyHeader: self::text($settings['apiKeyHeader'] ?? null) ?? 'X-Api-Key',
        );
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
