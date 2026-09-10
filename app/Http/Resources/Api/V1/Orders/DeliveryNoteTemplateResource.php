<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un modèle de BL proposé au moment de générer.
 *
 * Ni le corps, ni les variables : ce sont des LONGTEXT que l'écran de
 * génération n'affiche pas. Ce qu'il lui faut, c'est de quoi reconnaître le
 * modèle — son nom, son code — et de quoi comprendre **pourquoi** il est
 * proposé : sa portée, et la prestation qu'il vise.
 */
class DeliveryNoteTemplateResource extends JsonResource
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
            'scope' => $this->scope(),
            'customerId' => $this->customer_id,
            'serviceId' => $this->service_id,
            'language' => $this->language,
            'isDefault' => (bool) $this->is_default,
        ];
    }
}
