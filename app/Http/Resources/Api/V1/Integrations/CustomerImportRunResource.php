<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Integrations;

use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\Models\CustomerImportRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une exécution d'import, pour le back-office comme pour le portail.
 *
 * `forPortal` : le client ne voit pas le nom de l'employé du transporteur qui
 * a importé pour lui — seulement que l'import vient du transporteur.
 * `withErrors` : les commandes refusées (200 au plus), sur la fiche seulement.
 *
 * @mixin CustomerImportRun
 */
class CustomerImportRunResource extends JsonResource
{
    public function __construct(
        CustomerImportRun $resource,
        private readonly bool $forPortal = false,
        private readonly bool $withErrors = false,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $showTrigger = ! $this->forPortal || $this->channel === ImportRun::PORTAL;

        return [
            'id' => $this->id,
            'configuration' => $this->whenLoaded('configuration', fn (): array => [
                'id' => $this->configuration->id,
                'name' => $this->configuration->name,
            ]),
            'channel' => $this->channel,
            'source' => $this->source,
            'status' => $this->status,
            'fileName' => $this->file_name,
            'counts' => [
                'rows' => $this->row_count,
                'orders' => $this->order_count,
                'created' => $this->created_count,
                'updated' => $this->updated_count,
                'skipped' => $this->skipped_count,
                'invalid' => $this->invalid_count,
                'failed' => $this->failed_count,
            ],
            // Le détail des commandes refusées n'est rendu que sur la fiche.
            'errors' => $this->when($this->withErrors, fn (): array => $this->errors ?? []),
            'errorMessage' => $this->error_message,
            'triggeredBy' => $showTrigger && $this->relationLoaded('trigger') && $this->trigger !== null
                ? ['id' => $this->trigger->id, 'name' => $this->trigger->fullName()]
                : null,
            'startedAt' => $this->started_at?->toIso8601String(),
            'finishedAt' => $this->finished_at?->toIso8601String(),
        ];
    }
}
