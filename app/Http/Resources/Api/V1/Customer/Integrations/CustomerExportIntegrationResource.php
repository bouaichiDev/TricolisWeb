<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Integrations;

use App\Modules\Exports\Models\CustomerExportConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une configuration d'export, vue par le client : ce qui part, sous quel
 * format, par quel canal, à quel moment.
 *
 * **Aucune donnée de connexion** — ni `host`, ni `username`, ni
 * `encryptedPassword`, ni `settings` (qui portent en-têtes et paramètres
 * d'authentification). Le client n'administre pas les secrets du flux.
 *
 * @mixin CustomerExportConfiguration
 */
class CustomerExportIntegrationResource extends JsonResource
{
    public function __construct(CustomerExportConfiguration $resource, private readonly ?string $lastSentAt = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'exportType' => $this->export_type,
            'format' => $this->format?->value,
            'transport' => $this->transport?->value,
            'frequency' => $this->frequency,
            'isActive' => (bool) $this->is_active,
            'lastSentAt' => $this->lastSentAt,
        ];
    }
}
