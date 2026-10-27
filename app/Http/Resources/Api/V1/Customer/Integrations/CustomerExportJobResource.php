<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Integrations;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Exports\Services\Content\ExportContentFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un envoi vers le système du client, et son sort.
 *
 * Le message d'erreur est celui que le moteur écrit : un statut HTTP, une
 * raison — jamais un corps de réponse ni un secret. Le chemin de stockage du
 * fichier n'est pas exposé.
 *
 * `canRetry` est calculé ici : permission, envoi non transmis et en échec,
 * configuration active, contenu que le moteur sait reproduire.
 *
 * @mixin ExportJob
 */
class CustomerExportJobResource extends JsonResource
{
    public function __construct(ExportJob $resource, private readonly ?string $entityReference = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $configuration = $this->configuration;

        return [
            'id' => $this->id,
            'configuration' => $configuration === null ? null : [
                'id' => $configuration->id,
                'name' => $configuration->name,
                'exportType' => $configuration->export_type,
                'transport' => $configuration->transport?->value,
            ],
            'entityType' => $this->entity_type,
            'entityReference' => $this->entityReference,
            'fileName' => $this->file_name,
            'status' => $this->status,
            'attemptCount' => (int) $this->attempt_count,
            'generatedAt' => $this->generated_at?->toIso8601String(),
            'sentAt' => $this->sent_at?->toIso8601String(),
            'errorMessage' => $this->error_message,
            'actions' => [
                'canRetry' => app(CurrentCustomerContext::class)->can('customer.integrations.export_jobs.retry')
                    && $this->sent_at === null
                    && $this->status === 'failed'
                    && (bool) $configuration?->is_active
                    && app(ExportContentFactory::class)->supports($this->entity_type),
            ],
        ];
    }
}
