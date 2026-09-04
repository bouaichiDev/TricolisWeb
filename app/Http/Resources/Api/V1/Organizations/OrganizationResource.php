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
            // Le chemin du fichier ne sort pas : l'écran n'en a pas besoin, et
            // le publier révélerait la disposition du disque. Il lui suffit de
            // savoir s'il doit demander l'image.
            'hasLogo' => $this->logo_path !== null,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
