<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Exports;

use App\Modules\Exports\Models\ExportJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Exécution d'un export.
 *
 * **`storagePath` n'y figure plus** — corrigé en Phase 10. Le chemin de
 * stockage est une donnée interne : le publier révèle l'arborescence du serveur
 * de fichiers et le nommage des dépôts distants. `DocumentCompactResource` le
 * masquait déjà depuis la Phase 2 ; les deux ressources sont désormais
 * cohérentes. `hasFile` suffit à savoir si un fichier a été produit.
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
            'hasFile' => $this->storage_path !== null,
            'status' => $this->status,
            'attemptCount' => $this->attempt_count,
            'generatedAt' => $this->generated_at?->toIso8601String(),
            'sentAt' => $this->sent_at?->toIso8601String(),
            'errorMessage' => $this->error_message,
            'configuration' => new ExportConfigurationResource($this->whenLoaded('configuration')),
        ];
    }
}
