<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Exports;

use App\Modules\Exports\Models\ExportJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Exécution d'un export.
 *
 * @mixin ExportJob
 */
class ExportJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'configurationId' => $this->configuration_id,
            'entityType' => $this->entity_type,
            'entityId' => $this->entity_id,
            'fileName' => $this->file_name,
            'storagePath' => $this->storage_path,
            'status' => $this->status,
            'attemptCount' => $this->attempt_count,
            'generatedAt' => $this->generated_at?->toIso8601String(),
            'sentAt' => $this->sent_at?->toIso8601String(),
            'errorMessage' => $this->error_message,
            'configuration' => new ExportConfigurationResource($this->whenLoaded('configuration')),
        ];
    }
}
