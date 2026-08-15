<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Claims;

use App\Modules\Claims\Models\Claim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réclamation réduite à ce qu'affiche un rappel ou une liste déroulante.
 *
 * @mixin Claim
 */
class ClaimCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'claimType' => $this->claim_type,
            'status' => $this->status,
        ];
    }
}
