<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Integrations;

use App\Modules\Integrations\Models\CustomerApiConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un accès API du client, en lecture : nom, état, droits, dernière
 * utilisation.
 *
 * **Jamais `apiKeyHash`**, ni la clé : elle n'a été montrée qu'une fois, à sa
 * création ou sa rotation, dans le back-office du transporteur. La gestion
 * des clés n'est pas ouverte au portail (voir rapport de phase). La liste des
 * adresses autorisées n'est pas détaillée : son existence suffit au client.
 *
 * @mixin CustomerApiConfiguration
 */
class CustomerApiIntegrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'isActive' => (bool) $this->is_active,
            'permissions' => array_values($this->permissions ?? []),
            'restrictedByIp' => ($this->allowed_ips ?? []) !== [],
            'lastUsedAt' => $this->last_used_at?->toIso8601String(),
        ];
    }
}
