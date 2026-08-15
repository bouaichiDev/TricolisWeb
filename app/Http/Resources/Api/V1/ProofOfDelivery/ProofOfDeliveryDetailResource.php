<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\ProofOfDelivery;

use App\Http\Resources\Api\V1\Documents\DocumentCompactResource;
use App\Http\Resources\Api\V1\Identity\UserCompactResource;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'une preuve de livraison.
 *
 * Signature et photo sont restituées comme documents compacts lorsqu'elles sont
 * chargées — jamais comme chemins de fichiers : le stockage relève du module
 * Documents.
 *
 * @mixin ProofOfDelivery
 */
class ProofOfDeliveryDetailResource extends JsonResource
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
            'remark' => $this->remark,
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'createdBy' => $this->created_by,
            'signatureDocument' => new DocumentCompactResource($this->whenLoaded('signatureDocument')),
            'photoDocument' => new DocumentCompactResource($this->whenLoaded('photoDocument')),
            'creator' => new UserCompactResource($this->whenLoaded('creator')),
        ];
    }
}
