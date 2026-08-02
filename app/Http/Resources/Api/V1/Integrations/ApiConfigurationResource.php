<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Integrations;

use App\Modules\Integrations\Models\CustomerApiConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Accès API client.
 *
 * **`apiKeyHash` n'y figure pas**, et ne doit jamais y figurer : la clé n'est
 * restituée qu'une fois, à la création ou à la rotation, par
 * `ApiKeyIssuedResource`.
 *
 * @mixin CustomerApiConfiguration
 */
class ApiConfigurationResource extends JsonResource
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
            'allowedIps' => $this->allowed_ips,
            'permissions' => $this->permissions,
            'isActive' => $this->is_active,
            'lastUsedAt' => $this->last_used_at?->toIso8601String(),
        ];
    }
}
