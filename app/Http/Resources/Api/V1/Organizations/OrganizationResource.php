<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Organizations;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationResource extends JsonResource
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
            'legalName' => $this->legal_name,
            'registrationNumber' => $this->registration_number,
            'taxNumber' => $this->tax_number,
            'email' => $this->email,
            'phone' => $this->phone,
            'preferredLanguage' => $this->preferred_language,
            'timezone' => $this->timezone,
            'currencyCode' => $this->currency_code,
            'status' => $this->status?->value,
            'settings' => $this->settings,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
