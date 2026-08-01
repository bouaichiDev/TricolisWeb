<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Providers;

use App\Modules\Providers\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fournisseur réduit à ce qu'affiche une liste déroulante ou un rappel.
 *
 * @mixin Provider
 */
class ProviderCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'providerType' => $this->provider_type,
            'status' => $this->status,
        ];
    }
}
