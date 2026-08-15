<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\ProofOfDelivery;

use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Preuve de livraison vue depuis une liste.
 *
 * Les deux documents ne sont exposés que par leur identifiant : la liste n'a pas
 * à charger leurs métadonnées.
 *
 * @mixin ProofOfDelivery
 */
class ProofOfDeliveryListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'orderServiceId' => $this->order_service_id,
            'tourStopId' => $this->tour_stop_id,
            'recipientName' => $this->recipient_name,
            'signatureDocumentId' => $this->signature_document_id,
            'photoDocumentId' => $this->photo_document_id,
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'createdBy' => $this->created_by,
        ];
    }
}
