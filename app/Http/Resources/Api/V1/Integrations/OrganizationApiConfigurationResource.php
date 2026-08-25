<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Integrations;

use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une API externe appelée par l'organisme, **sans son secret**.
 *
 * `hasCredentials` dit qu'un secret est posé ; il ne ressort jamais. Un secret
 * qui traverse une réponse JSON finit dans un journal de proxy, un cache de
 * navigateur ou une capture d'écran — et il n'y a aucune raison de le relire :
 * on le remplace, on ne le consulte pas.
 *
 * @mixin OrganizationApiConfiguration
 */
class OrganizationApiConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'code' => $this->code,
            'name' => $this->name,
            'baseUrl' => $this->base_url,
            'authType' => $this->auth_type,
            'hasCredentials' => $this->hasCredentials(),
            'headers' => $this->headers,
            'timeoutSeconds' => $this->timeout_seconds,
            'isActive' => $this->is_active,
            'lastUsedAt' => $this->last_used_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
