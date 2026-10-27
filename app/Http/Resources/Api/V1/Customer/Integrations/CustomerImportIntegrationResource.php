<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Integrations;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Mapping\MappingPairs;
use App\Modules\Integrations\Services\Sources\ImportSourceKind;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une configuration d'import, vue par le client.
 *
 * Ce qui décrit le flux — nom, canal, format, fréquence, état, dernière
 * exécution — et, avec `customer.integrations.mapping.view`, la
 * correspondance mise à plat. **Jamais** de connexion : ni hôte, ni
 * identifiant, ni secret, ni réglage technique.
 *
 * @mixin CustomerImportConfiguration
 */
class CustomerImportIntegrationResource extends JsonResource
{
    public function __construct(CustomerImportConfiguration $resource, private readonly ?string $lastRunAt = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = app(CurrentCustomerContext::class);
        $channel = ImportSourceKind::of($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sourceType' => $this->source_type,
            'fileFormat' => $this->file_format,
            'channel' => $channel,
            'frequency' => $channel === ImportSourceKind::UPLOAD ? 'manual' : 'hourly',
            'isActive' => (bool) $this->is_active,
            'lastRunAt' => $this->lastRunAt,
            'actions' => [
                'canUpload' => $this->is_active
                    && $channel === ImportSourceKind::UPLOAD
                    && ImportSourceKind::acceptsUpload($this->resource)
                    && is_array($this->mapping) && $this->mapping !== []
                    && $context->can('customer.integrations.import'),
            ],
            'mapping' => $context->can('customer.integrations.mapping.view')
                ? MappingPairs::of($this->mapping)
                : null,
        ];
    }
}
