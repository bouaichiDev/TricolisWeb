<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Integrations;

use App\Modules\Integrations\Models\CustomerImportConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configuration d'import.
 *
 * @mixin CustomerImportConfiguration
 */
class ImportConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'name' => $this->name,
            'sourceType' => $this->source_type,
            'fileFormat' => $this->file_format,
            'mapping' => $this->mapping,
            'validationRules' => $this->validation_rules,
            'isActive' => $this->is_active,
        ];
    }
}
